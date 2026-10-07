<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Ai\BillReadException;
use App\Services\Ai\GeminiBillReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Extra charges on an open invoice: manual, or read from a utility bill by AI. */
class InvoiceChargeController extends Controller
{
    public static function scanKey(Invoice $invoice): string
    {
        return "bill_scan.{$invoice->id}";
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeEditable($invoice);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ]);

        $invoice->items()->create($data);
        $invoice->recalculateTotal();
        $request->session()->forget(self::scanKey($invoice));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Charge added.']);

        return back();
    }

    public function destroy(Invoice $invoice, InvoiceItem $item): RedirectResponse
    {
        $this->authorizeEditable($invoice);
        abort_unless($item->invoice_id === $invoice->id, 404);

        if ($invoice->items()->count() <= 1) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'An invoice needs at least one item. Void it instead.']);

            return back();
        }

        $item->delete();
        $invoice->recalculateTotal();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Charge removed.']);

        return back();
    }

    /** Upload a TNB / water bill; Gemini reads it; landlord confirms on the invoice page. */
    public function scan(Request $request, Invoice $invoice, GeminiBillReader $reader): RedirectResponse
    {
        $this->authorizeEditable($invoice);

        $request->validate([
            'bill' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:5120'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('bill');

        // Keep the bill as proof for the tenant (private disk).
        $invoice->attachments()->create([
            'path' => $file->store('bills', 'local'),
            'original_name' => $file->getClientOriginalName(),
        ]);

        try {
            $result = $reader->read((string) file_get_contents($file->getRealPath()), (string) $file->getMimeType());
        } catch (BillReadException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        $request->session()->put(self::scanKey($invoice), [
            ...$result,
            'suggested_description' => $this->describe($result),
        ]);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Bill read. Please check the details before adding.']);

        return back();
    }

    public function dismiss(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        $request->session()->forget(self::scanKey($invoice));

        return back();
    }

    /** @param array{provider: string|null, type: string|null, period: string|null} $r */
    private function describe(array $r): string
    {
        $label = match ($r['type']) {
            'electricity' => 'Electricity',
            'water' => 'Water',
            'sewerage' => 'Sewerage',
            default => 'Utility',
        };
        $provider = $r['provider'] ? " ({$r['provider']})" : '';
        $period = $r['period'] ? ' '.CarbonImmutable::createFromFormat('Y-m', $r['period'])?->format('M Y') : '';

        return mb_substr($label.$provider.$period, 0, 120);
    }

    private function authorizeEditable(Invoice $invoice): void
    {
        Gate::authorize('update', $invoice);

        $open = in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Overdue], true);
        $paidSomething = $invoice->payments()->where('status', PaymentStatus::Success)->exists();

        abort_if(! $open || $paidSomething, 403, 'Charges can only change on unpaid invoices with no payments.');
    }
}
