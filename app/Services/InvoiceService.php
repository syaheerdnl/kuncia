<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenancy;
use App\Services\Utilities\UtilityBillService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private readonly UtilityBillService $utilities) {}

    /**
     * Create the monthly invoice for one tenancy. Returns null when the tenancy
     * does not cover that month or the invoice already exists (safe to re-run).
     */
    public function generateFor(Tenancy $tenancy, CarbonInterface $period): ?Invoice
    {
        $start = CarbonImmutable::parse($period)->startOfMonth();
        $end = $start->endOfMonth();

        $covers = $tenancy->start_date->lte($end)
            && ($tenancy->end_date === null || $tenancy->end_date->gte($start));

        if (! $covers || $tenancy->invoices()->whereDate('period', $start)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($tenancy, $start) {
            $invoice = $tenancy->invoices()->create([
                'invoice_no' => Invoice::nextNumber($start),
                'period' => $start,
                'issue_date' => $start,
                'due_date' => $start->day(min($tenancy->due_day, $start->daysInMonth)),
                'status' => InvoiceStatus::Unpaid,
            ]);

            $invoice->items()->create([
                'description' => 'Monthly rent - '.$start->format('F Y'),
                'amount' => $tenancy->monthly_rent,
            ]);
            $invoice->recalculateTotal();
            $this->utilities->attachPending($invoice);

            return $invoice;
        });
    }

    /** Invoice every active tenancy for the month. Returns how many were created. */
    public function generateMonth(CarbonInterface $period): int
    {
        $created = 0;

        Tenancy::active()->get()->each(function (Tenancy $tenancy) use ($period, &$created) {
            if ($this->generateFor($tenancy, $period)) {
                $created++;
            }
        });

        return $created;
    }

    /** Flag unpaid invoices past due and add the late fee once. */
    public function markOverdue(CarbonInterface $today): int
    {
        $count = 0;
        $fee = (float) config('sewahub.late_fee', 20);

        Invoice::where('status', InvoiceStatus::Unpaid)
            ->whereDate('due_date', '<', $today->toDateString())
            ->get() // not chunked: we change the filtered column while looping
            ->each(function (Invoice $invoice) use ($fee, &$count) {
                DB::transaction(function () use ($invoice, $fee) {
                    $invoice->update(['status' => InvoiceStatus::Overdue]);
                    if ($fee > 0) {
                        $invoice->items()->create(['description' => 'Late payment fee', 'amount' => $fee]);
                        $invoice->recalculateTotal();
                    }
                });
                $count++;
            });

        return $count;
    }

    public function outstanding(Invoice $invoice): float
    {
        $paid = (float) $invoice->payments()->where('status', PaymentStatus::Success)->sum('amount');

        return round((float) $invoice->total - $paid, 2);
    }

    /** Record a successful payment; closes the invoice once fully paid. */
    public function recordPayment(
        Invoice $invoice,
        float $amount,
        PaymentMethod $method,
        ?string $reference = null,
        ?CarbonInterface $paidAt = null,
        ?string $billCode = null,
    ): Payment {
        return DB::transaction(function () use ($invoice, $amount, $method, $reference, $paidAt, $billCode) {
            $paidAt ??= now();

            $payment = $invoice->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'bill_code' => $billCode,
                'status' => PaymentStatus::Success,
                'paid_at' => $paidAt,
            ]);

            if ($this->outstanding($invoice) <= 0) {
                $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => $paidAt]);
            }

            return $payment;
        });
    }

    /**
     * Mark a pending online payment as successful. Safe to call twice
     * (return URL and callback can both arrive).
     */
    public function confirmOnlinePayment(Payment $payment, ?string $reference = null): void
    {
        DB::transaction(function () use ($payment, $reference) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Success) {
                return;
            }

            $payment->update([
                'status' => PaymentStatus::Success,
                'reference' => $reference ?? $payment->reference,
                'paid_at' => now(),
            ]);

            $invoice = $payment->invoice;
            if ($this->outstanding($invoice) <= 0) {
                $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);
            }
        });
    }

    public function failOnlinePayment(Payment $payment): void
    {
        if ($payment->status === PaymentStatus::Pending) {
            $payment->update(['status' => PaymentStatus::Failed]);
        }
    }

    public function void(Invoice $invoice): void
    {
        if ($invoice->payments()->where('status', PaymentStatus::Success)->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Invoices with payments cannot be voided.']);
        }

        DB::transaction(function () use ($invoice) {
            $invoice->update(['status' => InvoiceStatus::Void]);
            $this->utilities->release($invoice);
        });
    }
}
