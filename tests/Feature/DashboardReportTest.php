<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15');
    $this->tenancy = Tenancy::factory()->create(['start_date' => '2026-08-01', 'end_date' => '2026-11-30', 'monthly_rent' => 500]);
    $this->landlord = $this->tenancy->unit->property->owner;
    $this->tenant = $this->tenancy->tenant;

    $svc = app(InvoiceService::class);
    $sep = $svc->generateFor($this->tenancy, CarbonImmutable::parse('2026-09-01'));
    $svc->recordPayment($sep, 500, PaymentMethod::Cash, null, CarbonImmutable::parse('2026-09-05'));
    $oct = $svc->generateFor($this->tenancy, CarbonImmutable::parse('2026-10-01'));
    $svc->recordPayment($oct, 200, PaymentMethod::Cash, null, CarbonImmutable::parse('2026-10-03'));
    $oct->update(['status' => InvoiceStatus::Overdue]);
});

test('landlord dashboard shows cards, trend, overdue and ending leases', function () {
    MaintenanceRequest::factory()->create(['unit_id' => $this->tenancy->unit_id, 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->landlord)->get(route('dashboard'))
        ->assertInertia(fn (Assert $p) => $p
            ->component('dashboard')
            ->where('view', 'landlord')
            ->where('cards.occupancy', 100)
            ->where('cards.collected_month', fn ($v) => (float) $v === 200.0)
            ->where('cards.outstanding', fn ($v) => (float) $v === 300.0)
            ->where('cards.open_tickets', 1)
            ->has('cards.overdue_count')
            ->has('cards.high_tickets')
            ->has('trend', 6)
            ->where('trend.5.month', '2026-10')
            ->where('trend.4.collected', fn ($v) => (float) $v === 500.0)
            ->has('overdue', 1)
            ->has('endingLeases', 1));
});

test('landlord dashboard ignores other landlords data', function () {
    $other = User::factory()->landlord()->create();

    $this->actingAs($other)->get(route('dashboard'))
        ->assertInertia(fn (Assert $p) => $p
            ->where('cards.units', 0)
            ->where('cards.outstanding', fn ($v) => (float) $v === 0.0)
            ->has('overdue', 0));
});

test('tenant dashboard shows amount due after partial payment', function () {
    $this->actingAs($this->tenant)->get(route('dashboard'))
        ->assertInertia(fn (Assert $p) => $p
            ->where('view', 'tenant')
            ->where('amountDue', fn ($v) => (float) $v === 300.0)
            ->where('nextDue', '2026-10-07')
            ->has('openInvoices', 1));
});

test('maintenance dashboard lists only my open jobs', function () {
    $tech = User::factory()->maintenance()->create(['landlord_id' => $this->landlord->id]);
    MaintenanceRequest::factory()->create(['unit_id' => $this->tenancy->unit_id, 'tenant_id' => $this->tenant->id, 'assigned_to' => $tech->id]);
    MaintenanceRequest::factory()->create(['unit_id' => $this->tenancy->unit_id, 'tenant_id' => $this->tenant->id]);

    $this->actingAs($tech)->get(route('dashboard'))
        ->assertInertia(fn (Assert $p) => $p->where('view', 'maintenance')->has('tickets', 1));
});

test('income report groups payments by property and month, and exports csv', function () {
    $this->actingAs($this->landlord)->get(route('reports.index', ['year' => 2026]))
        ->assertInertia(fn (Assert $p) => $p
            ->component('reports/index')
            ->has('report.rows', 12)
            ->where('report.rows.8.'.$this->tenancy->unit->property->name, fn ($v) => (float) $v === 500.0)
            ->where('report.rows.9.'.$this->tenancy->unit->property->name, fn ($v) => (float) $v === 200.0));

    $csv = $this->actingAs($this->landlord)->get(route('reports.export', ['year' => 2026]))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('2026-09')->toContain('500.00')->toContain('Total');
});

test('reports are landlord only', function () {
    $this->actingAs($this->tenant)->get(route('reports.index'))->assertForbidden();
});
