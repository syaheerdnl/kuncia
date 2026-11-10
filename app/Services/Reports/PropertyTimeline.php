<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Enums\TenancyStatus;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenancy;

/**
 * What happened at one property, newest first: money received and tenants
 * moving in or out. The page shows them as a vertical timeline.
 *
 * Every event is wrapped with a sort key. On the same day the order reads
 * backwards in time: move-out first, then payments, then the move-in.
 *
 * @phpstan-type Event array<string, string|null>
 * @phpstan-type Sortable array{sort: string, event: Event}
 */
class PropertyTimeline
{
    /** More than this is never sent to the page, however long the history. */
    public const LIMIT = 300;

    /**
     * @return list<Event>
     */
    public function for(Property $property): array
    {
        $all = array_merge(
            $this->payments($property),
            $this->tenancyStarts($property),
            $this->tenancyEnds($property),
        );

        usort($all, fn (array $a, array $b) => strcmp($b['sort'], $a['sort']));

        return array_map(
            fn (array $item) => $item['event'],
            array_slice($all, 0, self::LIMIT),
        );
    }

    /** @return list<Sortable> */
    private function payments(Property $property): array
    {
        $payments = Payment::query()
            ->where('status', PaymentStatus::Success)
            ->whereNotNull('paid_at')
            ->whereHas('invoice.tenancy.unit', fn ($q) => $q->where('property_id', $property->id))
            ->with('invoice.tenancy.tenant', 'invoice.tenancy.unit')
            ->get();

        $out = [];

        foreach ($payments as $payment) {
            if ($payment->paid_at === null) {
                continue;
            }

            $date = $payment->paid_at->toDateString();
            $tenancy = $payment->invoice->tenancy;

            $out[] = [
                'sort' => $date.'-2-'.$this->pad($payment->id),
                'event' => [
                    'type' => 'payment',
                    'date' => $date,
                    'amount' => $payment->amount,
                    'method' => $payment->method->label(),
                    'reference' => $payment->reference,
                    'period' => $payment->invoice->period->format('M Y'),
                    'tenant' => $tenancy->tenant->name,
                    'unit' => $tenancy->unit->code,
                ],
            ];
        }

        return $out;
    }

    /** @return list<Sortable> */
    private function tenancyStarts(Property $property): array
    {
        $out = [];

        foreach ($this->tenancies($property) as $tenancy) {
            $date = $tenancy->start_date->toDateString();

            $out[] = [
                'sort' => $date.'-1-'.$this->pad($tenancy->id),
                'event' => [
                    'type' => 'tenancy_start',
                    'date' => $date,
                    'amount' => $tenancy->monthly_rent,
                    'deposit' => $tenancy->deposit_amount,
                    'tenant' => $tenancy->tenant->name,
                    'unit' => $tenancy->unit->code,
                ],
            ];
        }

        return $out;
    }

    /**
     * A fixed end date on a tenancy that is still running is not an event yet.
     *
     * @return list<Sortable>
     */
    private function tenancyEnds(Property $property): array
    {
        $out = [];

        foreach ($this->tenancies($property) as $tenancy) {
            if ($tenancy->status === TenancyStatus::Active || $tenancy->end_date === null) {
                continue;
            }

            $date = $tenancy->end_date->toDateString();

            $out[] = [
                'sort' => $date.'-3-'.$this->pad($tenancy->id),
                'event' => [
                    'type' => 'tenancy_end',
                    'date' => $date,
                    'amount' => $tenancy->monthly_rent,
                    'deposit' => $tenancy->deposit_amount,
                    'deposit_status' => $tenancy->deposit_status->label(),
                    'tenant' => $tenancy->tenant->name,
                    'unit' => $tenancy->unit->code,
                ],
            ];
        }

        return $out;
    }

    /** @return iterable<Tenancy> */
    private function tenancies(Property $property): iterable
    {
        return Tenancy::query()
            ->whereHas('unit', fn ($q) => $q->where('property_id', $property->id))
            ->with('tenant', 'unit')
            ->get();
    }

    private function pad(int $id): string
    {
        return str_pad((string) $id, 10, '0', STR_PAD_LEFT);
    }
}
