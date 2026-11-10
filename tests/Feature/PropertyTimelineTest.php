<?php

use App\Enums\PaymentMethod;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Reports\PropertyTimeline;
use App\Services\Utilities\UtilityBillService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->property = Property::factory()->create();
    $this->unit = Unit::factory()->create(['property_id' => $this->property->id, 'code' => 'A']);
    $this->invoices = app(InvoiceService::class);
    $this->timeline = app(PropertyTimeline::class);

    $this->rent = fn (string $start) => Tenancy::factory()->create([
        'unit_id' => $this->unit->id,
        'start_date' => $start,
        'end_date' => '2027-06-30',
        'monthly_rent' => 400,
        'due_day' => 5,
    ]);
});

test('each month splits what was billed by type and shows what came in', function () {
    $this->travelTo('2026-07-01');
    $tenancy = ($this->rent)('2026-07-01');

    $july = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-07-01'));
    $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-08-01'));
    $this->invoices->recordPayment($july, 400, PaymentMethod::Transfer, null, CarbonImmutable::parse('2026-07-06'));

    // August is not paid by the 5th, so it turns overdue and gets the late fee.
    $this->travelTo('2026-08-20');
    $this->invoices->markOverdue(CarbonImmutable::today());

    // September has an extra charge and a share of an August meter bill.
    $september = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-09-01'));
    $september->items()->create(['description' => 'Indah Water', 'amount' => 50]);
    $september->recalculateTotal();

    $meter = $this->property->meters()->create(['type' => 'electricity', 'label' => 'TNB']);
    $meter->units()->sync([$this->unit->id]);
    app(UtilityBillService::class)->record($meter, CarbonImmutable::parse('2026-08-01'), 90);

    $this->travelTo('2026-09-02');
    $rows = $this->timeline->for($this->property, CarbonImmutable::today());

    expect($rows)->toHaveCount(3)
        ->and(array_column($rows, 'label'))->toBe(['Jul 2026', 'Aug 2026', 'Sep 2026'])
        ->and($rows[0])->toMatchArray([
            'rent' => 400.0, 'utilities' => 0.0, 'late_fee' => 0.0, 'other' => 0.0,
            'billed' => 400.0, 'collected' => 400.0, 'paid' => 1, 'unpaid' => 0, 'overdue' => 0,
        ])
        ->and($rows[1])->toMatchArray([
            'rent' => 400.0, 'late_fee' => 20.0, 'billed' => 420.0,
            'collected' => 0.0, 'paid' => 0, 'overdue' => 1,
        ])
        ->and($rows[2])->toMatchArray([
            'rent' => 400.0, 'utilities' => 90.0, 'other' => 50.0, 'billed' => 540.0,
            'collected' => 0.0, 'unpaid' => 1,
        ]);
});

test('a late payment counts in the month it arrived, not the month it was owed', function () {
    $this->travelTo('2026-08-01');
    $tenancy = ($this->rent)('2026-08-01');
    $august = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-08-01'));

    $this->travelTo('2026-08-20');
    $this->invoices->markOverdue(CarbonImmutable::today()); // total is now 420 with the late fee
    $this->invoices->recordPayment($august->fresh(), 420, PaymentMethod::Cash, null, CarbonImmutable::parse('2026-09-01'));

    $this->travelTo('2026-09-02');
    $rows = $this->timeline->for($this->property, CarbonImmutable::today());

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray(['billed' => 420.0, 'collected' => 0.0, 'paid' => 1])
        ->and($rows[1])->toMatchArray(['billed' => 0.0, 'collected' => 420.0]);
});

test('voided invoices and other properties are left out', function () {
    $this->travelTo('2026-08-01');
    $tenancy = ($this->rent)('2026-08-01');
    $voided = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-08-01'));
    $this->invoices->void($voided);

    $elsewhere = Tenancy::factory()->create(['start_date' => '2026-08-01', 'end_date' => '2027-06-30']);
    $this->invoices->generateFor($elsewhere, CarbonImmutable::parse('2026-08-01'));

    expect($this->timeline->for($this->property, CarbonImmutable::today()))->toBe([]);
});

test('a property with no invoices has an empty timeline', function () {
    expect($this->timeline->for($this->property, CarbonImmutable::today()))->toBe([]);
});

test('the property page carries the timeline for its owner only', function () {
    $this->travelTo('2026-08-01');
    $tenancy = ($this->rent)('2026-08-01');
    $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-08-01'));

    $this->actingAs($this->property->owner)
        ->get(route('properties.show', $this->property))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('properties/show')
            ->has('timeline', 1)
            ->where('timeline.0.rent', 400)
        );

    $this->actingAs(User::factory()->landlord()->create())
        ->get(route('properties.show', $this->property))
        ->assertForbidden();
});
