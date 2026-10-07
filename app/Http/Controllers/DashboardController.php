<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Reports\LandlordStats;
use App\Support\InvoiceData;
use App\Support\TicketData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** One dashboard URL; content depends on the role. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, InvoiceService $invoices): Response
    {
        /** @var User $user */
        $user = $request->user();
        $today = CarbonImmutable::today();

        return match (true) {
            $user->isLandlord() => $this->landlord($user, $today),
            $user->isTenant() => $this->tenant($user, $invoices),
            default => $this->staff($user),
        };
    }

    private function landlord(User $user, CarbonImmutable $today): Response
    {
        $stats = new LandlordStats($user);

        return Inertia::render('dashboard', [
            'view' => 'landlord',
            'cards' => $stats->cards($today),
            'trend' => $stats->trend($today),
            'overdue' => $stats->overdue(),
            'endingLeases' => $stats->endingLeases($today),
            'tickets' => MaintenanceRequest::forLandlord($user)
                ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])
                ->with(['unit.property', 'tenant', 'assignee'])
                ->workOrder()
                ->limit(5)
                ->get()
                ->map(fn (MaintenanceRequest $t) => TicketData::row($t)),
        ]);
    }

    private function tenant(User $user, InvoiceService $invoices): Response
    {
        $tenancy = $user->activeTenancy()->with('unit.property')->first();
        $open = $user->invoices()
            ->whereIn('invoices.status', [InvoiceStatus::Unpaid, InvoiceStatus::Overdue])
            ->with(['tenancy.tenant', 'tenancy.unit.property'])
            ->orderBy('due_date')
            ->get();

        return Inertia::render('dashboard', [
            'view' => 'tenant',
            'unit' => $tenancy ? [
                'name' => $tenancy->unit->property->name.' · '.$tenancy->unit->code,
                'address' => $tenancy->unit->property->address.', '.$tenancy->unit->property->city,
                'rent' => $tenancy->monthly_rent,
                'due_day' => $tenancy->due_day,
                'end_date' => $tenancy->end_date?->toDateString(),
            ] : null,
            'amountDue' => round($open->sum(fn (Invoice $i) => $invoices->outstanding($i)), 2),
            'nextDue' => $open->first()?->due_date->toDateString(),
            'openInvoices' => $open->map(fn (Invoice $i) => InvoiceData::row($i))->values(),
            'openRequests' => $user->maintenanceRequests()
                ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])
                ->count(),
        ]);
    }

    private function staff(User $user): Response
    {
        return Inertia::render('dashboard', [
            'view' => 'maintenance',
            'tickets' => $user->assignedRequests()
                ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])
                ->with(['unit.property', 'tenant', 'assignee'])
                ->workOrder()
                ->get()
                ->map(fn (MaintenanceRequest $t) => TicketData::row($t)),
        ]);
    }
}
