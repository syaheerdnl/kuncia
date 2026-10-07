<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;

/** Shapes invoices for Inertia pages (shared by landlord + tenant views). */
class InvoiceData
{
    /** @return array<string, mixed> */
    public static function row(Invoice $invoice): array
    {
        $tenancy = $invoice->tenancy;

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'period' => $invoice->period->format('M Y'),
            'due_date' => $invoice->due_date->toDateString(),
            'total' => $invoice->total,
            'status' => $invoice->status->value,
            'tenant' => $tenancy->tenant->name,
            'unit' => $tenancy->unit->property->name.' · '.$tenancy->unit->code,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Invoice $invoice, float $outstanding): array
    {
        $invoice->loadMissing(['items', 'payments', 'attachments', 'tenancy.tenant', 'tenancy.unit.property']);

        return [
            ...self::row($invoice),
            'issue_date' => $invoice->issue_date->toDateString(),
            'paid_at' => $invoice->paid_at?->toDateTimeString(),
            'tenant_email' => $invoice->tenancy->tenant->email,
            'tenant_id' => $invoice->tenancy->tenant_id,
            'outstanding' => number_format($outstanding, 2, '.', ''),
            'items' => $invoice->items->map(fn (InvoiceItem $i) => [
                'id' => $i->id,
                'description' => $i->description,
                'amount' => $i->amount,
            ]),
            'bills' => $invoice->attachments->map(fn (Attachment $a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'url' => route('attachments.show', $a),
            ]),
            'payments' => $invoice->payments->sortByDesc('id')->values()->map(fn (Payment $p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'method' => $p->method->label(),
                'reference' => $p->reference,
                'status' => $p->status->value,
                'paid_at' => $p->paid_at?->toDateString(),
            ]),
        ];
    }
}
