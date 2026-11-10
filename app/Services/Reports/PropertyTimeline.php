<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Property;
use Carbon\CarbonImmutable;

/**
 * Month-by-month money history of one property, for the timeline chart.
 *
 * "Billed" is grouped by the invoice's month and split by type. "Collected" is
 * grouped by the month the money actually arrived, so a late payment shows in
 * the month it was received, not the month it was owed. Voided invoices are left out.
 * Grouping is done in PHP to stay DB-agnostic, like LandlordStats.
 */
class PropertyTimeline
{
    /**
     * One row per month, from the first invoice or payment up to the current month.
     *
     * @return list<array<string, float|int|string>>
     */
    public function for(Property $property, CarbonImmutable $today): array
    {
        $invoices = Invoice::query()
            ->whereHas('tenancy.unit', fn ($q) => $q->where('property_id', $property->id))
            ->where('status', '!=', InvoiceStatus::Void)
            ->with('items.utilityShare')
            ->get();

        $payments = Payment::query()
            ->where('status', PaymentStatus::Success)
            ->whereHas('invoice.tenancy.unit', fn ($q) => $q->where('property_id', $property->id))
            ->get(['paid_at', 'amount']);

        $months = $invoices->map(fn (Invoice $i) => $i->period->format('Y-m'))
            ->merge($payments->map(fn (Payment $p) => (string) $p->paid_at?->format('Y-m')))
            ->filter()
            ->sort()
            ->values();

        if ($months->isEmpty()) {
            return [];
        }

        $first = CarbonImmutable::createFromFormat('!Y-m', (string) $months->first());
        $last = CarbonImmutable::createFromFormat('!Y-m', (string) $months->last());
        assert($first instanceof CarbonImmutable && $last instanceof CarbonImmutable);

        $end = $last->gt($today->startOfMonth()) ? $last : $today->startOfMonth();

        /** @var array<string, array<string, float|int|string>> $rows */
        $rows = [];
        for ($m = $first; $m->lte($end); $m = $m->addMonth()) {
            $rows[$m->format('Y-m')] = [
                'month' => $m->format('Y-m'),
                'label' => $m->format('M Y'),
                'rent' => 0.0,
                'utilities' => 0.0,
                'late_fee' => 0.0,
                'other' => 0.0,
                'billed' => 0.0,
                'collected' => 0.0,
                'paid' => 0,
                'unpaid' => 0,
                'overdue' => 0,
            ];
        }

        foreach ($invoices as $invoice) {
            $key = $invoice->period->format('Y-m');

            foreach ($invoice->items as $item) {
                $group = $this->group($item);
                $rows[$key][$group] = (float) $rows[$key][$group] + (float) $item->amount;
                $rows[$key]['billed'] = (float) $rows[$key]['billed'] + (float) $item->amount;
            }

            $status = match ($invoice->status) {
                InvoiceStatus::Paid => 'paid',
                InvoiceStatus::Overdue => 'overdue',
                default => 'unpaid',
            };
            $rows[$key][$status] = (int) $rows[$key][$status] + 1;
        }

        foreach ($payments as $payment) {
            $key = (string) $payment->paid_at?->format('Y-m');

            if (isset($rows[$key])) {
                $rows[$key]['collected'] = (float) $rows[$key]['collected'] + (float) $payment->amount;
            }
        }

        return array_values(array_map(function (array $row) {
            foreach (['rent', 'utilities', 'late_fee', 'other', 'billed', 'collected'] as $field) {
                $row[$field] = round((float) $row[$field], 2);
            }

            return $row;
        }, $rows));
    }

    /** Invoice lines have no type column, so tell them apart by what they are. */
    private function group(InvoiceItem $item): string
    {
        return match (true) {
            $item->utilityShare !== null => 'utilities',
            str_starts_with($item->description, InvoiceItem::RENT_PREFIX) => 'rent',
            $item->description === InvoiceItem::LATE_FEE => 'late_fee',
            default => 'other',
        };
    }
}
