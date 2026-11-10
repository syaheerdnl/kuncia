<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenancy;
use Carbon\CarbonImmutable;

/**
 * Month by month, what happened to the rent of each active tenancy on a property:
 * paid, unpaid, overdue, voided, or missing (nothing was ever invoiced for that month).
 * Missing is the gap the monthly job never fills for a tenancy that was added late.
 */
class RentCalendar
{
    /**
     * One entry per active tenancy, with a status for every month from the move-in month
     * to this month (or to the month the tenancy ends).
     *
     * @return list<array<string, mixed>>
     */
    public function for(Property $property, CarbonImmutable $today): array
    {
        $thisMonth = $today->startOfMonth();

        $tenancies = Tenancy::query()
            ->active()
            ->whereHas('unit', fn ($q) => $q->where('property_id', $property->id))
            ->with('tenant', 'unit', 'invoices')
            ->orderBy('start_date')
            ->get();

        $out = [];

        foreach ($tenancies as $tenancy) {
            $invoices = $tenancy->invoices->keyBy(fn (Invoice $i) => $i->period->format('Y-m'));
            $months = [];
            $missing = [];

            for ($m = $tenancy->start_date->startOfMonth(); $m->lte($thisMonth); $m = $m->addMonth()) {
                if ($tenancy->end_date !== null && $tenancy->end_date->lt($m)) {
                    break;
                }

                $key = $m->format('Y-m');
                $invoice = $invoices->get($key);

                $status = $invoice === null ? 'missing' : match ($invoice->status) {
                    InvoiceStatus::Paid => 'paid',
                    InvoiceStatus::Overdue => 'overdue',
                    InvoiceStatus::Void => 'void',
                    InvoiceStatus::Unpaid => 'unpaid',
                };

                if ($status === 'missing') {
                    $missing[] = ['month' => $key, 'label' => $m->format('F Y')];
                }

                $months[] = [
                    'month' => $key,
                    'label' => $m->format('F Y'),
                    'status' => $status,
                    'invoice_id' => $invoice?->id,
                ];
            }

            $out[] = [
                'id' => $tenancy->id,
                'label' => $tenancy->tenant->name.' · '.$tenancy->unit->code,
                'rent' => $tenancy->monthly_rent,
                'months' => $months,
                'missing' => $missing,
            ];
        }

        return $out;
    }
}
