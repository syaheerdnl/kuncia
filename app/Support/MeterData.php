<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UtilityBill;
use App\Models\UtilityBillShare;
use App\Models\UtilityMeter;

/** Shapes a property's meters and recent bills for the property page. */
class MeterData
{
    /** @return array<int, array<string, mixed>> */
    public static function forProperty(Property $property): array
    {
        $meters = $property->meters()
            ->with([
                'units' => fn ($q) => $q->orderBy('code'),
                'bills' => fn ($q) => $q->orderByDesc('period')->limit(50),
                'bills.attachments',
                'bills.shares.tenancy.tenant',
                'bills.shares.tenancy.unit',
                'bills.shares.invoiceItem.invoice',
            ])
            ->orderBy('label')
            ->get();

        return $meters->map(fn (UtilityMeter $m) => [
            'id' => $m->id,
            'type' => $m->type->value,
            'type_label' => $m->type->label(),
            'label' => $m->label,
            'account_no' => $m->account_no,
            'unit_ids' => $m->units->pluck('id')->all(),
            'unit_codes' => $m->units->map(fn (Unit $u) => $u->code)->all(),
            'bills' => $m->bills->take(6)->map(fn (UtilityBill $b) => self::bill($b))->values()->all(),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private static function bill(UtilityBill $bill): array
    {
        return [
            'id' => $bill->id,
            'period' => $bill->period->format('M Y'),
            'amount' => $bill->amount,
            'files' => $bill->attachments->map(fn (Attachment $a) => [
                'name' => $a->original_name,
                'url' => route('attachments.show', $a),
            ])->values()->all(),
            'shares' => $bill->shares->map(function (UtilityBillShare $s) {
                $invoice = $s->invoiceItem?->invoice;

                return [
                    'id' => $s->id,
                    'tenant' => $s->tenancy->tenant->name,
                    'unit' => $s->tenancy->unit->code,
                    'amount' => $s->amount,
                    'invoice_id' => $invoice?->id,
                    'invoice_no' => $invoice?->invoice_no,
                    'state' => match (true) {
                        $invoice === null => 'waiting',
                        $invoice->status->value === 'paid' => 'paid',
                        default => 'billed',
                    },
                ];
            })->values()->all(),
        ];
    }
}
