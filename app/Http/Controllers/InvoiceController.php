<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Invoice\RecordPaymentRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\InvoiceData;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request): Response
    {
        /** @var User $landlord */
        $landlord = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'month' => ['nullable', 'date_format:Y-m'],
            'property' => ['nullable', 'integer'],
        ]);

        $base = Invoice::forLandlord($landlord)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['month'] ?? null, fn ($q, $m) => $q->whereDate('period', $m.'-01'))
            ->when($filters['property'] ?? null, fn ($q, $p) => $q->whereHas('tenancy.unit', fn ($u) => $u->where('property_id', $p)));

        $summary = [
            'outstanding' => (string) (clone $base)->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Overdue])->sum('total'),
            'overdue_count' => (clone $base)->where('status', InvoiceStatus::Overdue)->count(),
            'collected' => (string) (clone $base)->where('status', InvoiceStatus::Paid)->sum('total'),
        ];

        $invoices = $base->with(['tenancy.tenant', 'tenancy.unit.property'])
            ->orderByDesc('period')->orderBy('invoice_no')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Invoice $i) => InvoiceData::row($i));

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
            'summary' => $summary,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'month' => $filters['month'] ?? '',
                'property' => isset($filters['property']) ? (string) $filters['property'] : '',
            ],
            'properties' => $landlord->properties()->orderBy('name')->get(['id', 'name']),
            'statuses' => InvoiceStatus::options(),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        Gate::authorize('update', $invoice);

        return Inertia::render('invoices/show', [
            'invoice' => InvoiceData::detail($invoice, $this->invoices->outstanding($invoice)),
            'canManage' => true,
        ]);
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->recordPayment(
            $invoice,
            (float) $request->validated('amount'),
            PaymentMethod::from((string) $request->validated('method')),
            $request->validated('reference'),
            CarbonImmutable::parse((string) $request->validated('paid_at')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment recorded.']);

        return back();
    }

    public function void(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        $this->invoices->void($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$invoice->invoice_no} voided."]);

        return back();
    }

    /** Landlord or the tenant can download. */
    public function pdf(Invoice $invoice): HttpResponse
    {
        Gate::authorize('view', $invoice);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice->load(['items', 'tenancy.tenant', 'tenancy.unit.property.owner']),
            'outstanding' => $this->invoices->outstanding($invoice),
        ])->download("{$invoice->invoice_no}.pdf");
    }
}
