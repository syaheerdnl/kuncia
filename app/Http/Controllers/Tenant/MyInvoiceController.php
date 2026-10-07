<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Payments\ToyyibPayClient;
use App\Support\InvoiceData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MyInvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request): Response
    {
        /** @var User $tenant */
        $tenant = $request->user();

        $invoices = $tenant->invoices()
            ->with(['tenancy.tenant', 'tenancy.unit.property'])
            ->latest('period')
            ->get()
            ->map(fn (Invoice $i) => InvoiceData::row($i));

        return Inertia::render('my/invoices', ['invoices' => $invoices]);
    }

    public function show(Invoice $invoice, ToyyibPayClient $toyyibpay): Response
    {
        Gate::authorize('view', $invoice);

        return Inertia::render('invoices/show', [
            'invoice' => InvoiceData::detail($invoice, $this->invoices->outstanding($invoice)),
            'canManage' => false,
            'onlinePayment' => $toyyibpay->configured(),
        ]);
    }
}
