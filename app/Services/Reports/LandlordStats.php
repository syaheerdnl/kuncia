<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenancyStatus;
use App\Enums\UnitStatus;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Numbers for the landlord dashboard and reports. Grouping is done in PHP to stay DB-agnostic. */
class LandlordStats
{
    public function __construct(private User $landlord) {}

    /** @return Builder<Payment> */
    private function payments(): Builder
    {
        return Payment::where('status', PaymentStatus::Success)
            ->whereHas('invoice', fn ($q) => $q->forLandlord($this->landlord));
    }

    /** @return array{units: int, occupied: int, occupancy: int, collected_month: float, outstanding: float, open_tickets: int} */
    public function cards(CarbonImmutable $today): array
    {
        $units = Unit::whereHas('property', fn ($q) => $q->where('owner_id', $this->landlord->id));
        $total = (clone $units)->count();
        $occupied = (clone $units)->where('status', UnitStatus::Occupied)->count();

        $open = Invoice::forLandlord($this->landlord)->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Overdue]);
        $billedOpen = (float) (clone $open)->sum('total');
        $paidOnOpen = (float) Payment::where('status', PaymentStatus::Success)
            ->whereIn('invoice_id', (clone $open)->select('id'))
            ->sum('amount');

        return [
            'units' => $total,
            'occupied' => $occupied,
            'occupancy' => $total ? (int) round($occupied / $total * 100) : 0,
            'collected_month' => round((float) $this->payments()
                ->whereBetween('paid_at', [$today->startOfMonth(), $today->endOfMonth()])
                ->sum('amount'), 2),
            'outstanding' => round($billedOpen - $paidOnOpen, 2),
            'open_tickets' => MaintenanceRequest::forLandlord($this->landlord)
                ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])
                ->count(),
        ];
    }

    /**
     * Billed (by invoice month) vs collected (by payment month), last N months.
     *
     * @return list<array{month: string, label: string, billed: float, collected: float}>
     */
    public function trend(CarbonImmutable $today, int $months = 6): array
    {
        $from = $today->startOfMonth()->subMonths($months - 1);

        $billed = Invoice::forLandlord($this->landlord)
            ->where('status', '!=', InvoiceStatus::Void)
            ->whereDate('period', '>=', $from->toDateString())
            ->get(['period', 'total'])
            ->groupBy(fn (Invoice $i) => $i->period->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('total'));

        $collected = $this->payments()
            ->where('paid_at', '>=', $from)
            ->get(['paid_at', 'amount'])
            ->groupBy(fn (Payment $p) => (string) $p->paid_at?->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('amount'));

        $rows = [];
        for ($m = $from; $m->lte($today); $m = $m->addMonth()) {
            $key = $m->format('Y-m');
            $rows[] = [
                'month' => $key,
                'label' => $m->format('M'),
                'billed' => round($billed[$key] ?? 0, 2),
                'collected' => round($collected[$key] ?? 0, 2),
            ];
        }

        return $rows;
    }

    /**
     * Money collected per property per month for one year.
     *
     * @return array{properties: list<string>, rows: list<array<string, float|string>>, totals: array<string, float>}
     */
    public function incomeByProperty(int $year): array
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();

        $payments = $this->payments()
            ->whereBetween('paid_at', [$start, $start->endOfYear()])
            ->with('invoice.tenancy.unit.property')
            ->get();

        /** @var list<string> $properties */
        $properties = $this->landlord->properties()->orderBy('name')->pluck('name')->map(fn ($n) => (string) $n)->values()->all();
        /** @var list<array<string, float|string>> $rows */
        $rows = [];
        /** @var array<string, float> $totals */
        $totals = array_fill_keys($properties, 0.0);

        for ($m = 1; $m <= 12; $m++) {
            $month = $start->month($m);
            $row = ['month' => $month->format('Y-m'), 'label' => $month->format('M')];
            foreach ($properties as $name) {
                $row[$name] = 0.0;
            }
            $rows[] = $row;
        }

        foreach ($payments as $p) {
            $name = $p->invoice->tenancy->unit->property->name;
            $idx = (int) $p->paid_at?->format('n') - 1;
            if (isset($rows[$idx][$name])) {
                $rows[$idx][$name] = round((float) $rows[$idx][$name] + (float) $p->amount, 2);
                $totals[$name] = round($totals[$name] + (float) $p->amount, 2);
            }
        }

        return ['properties' => $properties, 'rows' => $rows, 'totals' => $totals];
    }

    /** @return array<int, array<string, mixed>> */
    public function overdue(int $limit = 5): array
    {
        return Invoice::forLandlord($this->landlord)
            ->where('status', InvoiceStatus::Overdue)
            ->with('tenancy.tenant', 'tenancy.unit')
            ->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'invoice_no' => $i->invoice_no,
                'tenant' => $i->tenancy->tenant->name,
                'unit' => $i->tenancy->unit->code,
                'total' => $i->total,
                'days' => (int) $i->due_date->diffInDays(now()),
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function endingLeases(CarbonImmutable $today, int $days = 60): array
    {
        return Tenancy::where('status', TenancyStatus::Active)
            ->whereHas('unit.property', fn ($q) => $q->where('owner_id', $this->landlord->id))
            ->whereBetween('end_date', [$today->toDateString(), $today->addDays($days)->toDateString()])
            ->with('tenant', 'unit')
            ->orderBy('end_date')
            ->get()
            ->map(fn (Tenancy $t) => [
                'id' => $t->id,
                'tenant_id' => $t->tenant_id,
                'tenant' => $t->tenant->name,
                'unit' => $t->unit->code,
                'end_date' => $t->end_date?->toDateString(),
            ])->values()->all();
    }
}
