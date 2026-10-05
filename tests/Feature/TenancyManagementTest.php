<?php

use App\Enums\DepositStatus;
use App\Enums\TenancyStatus;
use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->landlord = User::factory()->landlord()->create();
    $this->property = Property::factory()->for($this->landlord, 'owner')->create();
    $this->unit = Unit::factory()->for($this->property)->create(['monthly_rent' => 350, 'deposit' => 700]);
    $this->tenant = User::factory()->tenant()->create(['landlord_id' => $this->landlord->id]);
});

function tenancyPayload(array $overrides = []): array
{
    return [
        'tenant_id' => test()->tenant->id,
        'unit_id' => test()->unit->id,
        'start_date' => '2026-11-01',
        'end_date' => '2027-10-31',
        'monthly_rent' => 350,
        'deposit_amount' => 700,
        'due_day' => 7,
        ...$overrides,
    ];
}

test('landlord adds a tenant who belongs to them', function () {
    $this->actingAs($this->landlord)->post(route('tenants.store'), [
        'name' => 'Siti Aminah', 'email' => 'siti@example.com', 'phone' => '012-3456789',
    ])->assertRedirect();

    $siti = User::where('email', 'siti@example.com')->first();
    expect($siti->isTenant())->toBeTrue()->and($siti->landlord_id)->toBe($this->landlord->id);
});

test('phone must be a Malaysian mobile number', function () {
    $this->actingAs($this->landlord)->post(route('tenants.store'), [
        'name' => 'X', 'email' => 'x@example.com', 'phone' => '12345',
    ])->assertSessionHasErrors('phone');
});

test('tenant list shows only my tenants and searches', function () {
    User::factory()->tenant()->create(['name' => 'Someone Else']); // other landlord
    User::factory()->tenant()->create(['name' => 'Zul Hakimi', 'landlord_id' => $this->landlord->id]);

    $this->actingAs($this->landlord)->get(route('tenants.index'))
        ->assertInertia(fn (Assert $p) => $p->component('tenants/index')->has('tenants', 2));

    $this->actingAs($this->landlord)->get(route('tenants.index', ['q' => 'zul']))
        ->assertInertia(fn (Assert $p) => $p->has('tenants', 1)->where('tenants.0.name', 'Zul Hakimi'));
});

test('another landlord cannot view my tenant', function () {
    $intruder = User::factory()->landlord()->create();

    $this->actingAs($intruder)->get(route('tenants.show', $this->tenant))->assertForbidden();
    $this->actingAs($this->landlord)->get(route('tenants.show', $this->tenant))->assertOk();
});

test('starting a tenancy occupies the unit', function () {
    $this->actingAs($this->landlord)->post(route('tenancies.store'), tenancyPayload())
        ->assertRedirect(route('tenants.show', $this->tenant));

    $tenancy = Tenancy::first();
    expect($tenancy->status)->toBe(TenancyStatus::Active)
        ->and($tenancy->deposit_status)->toBe(DepositStatus::Held)
        ->and($this->unit->fresh()->status)->toBe(UnitStatus::Occupied);
});

test('the same unit cannot be booked twice', function () {
    $this->actingAs($this->landlord)->post(route('tenancies.store'), tenancyPayload());

    $other = User::factory()->tenant()->create(['landlord_id' => $this->landlord->id]);
    $this->actingAs($this->landlord)->post(route('tenancies.store'), tenancyPayload(['tenant_id' => $other->id]))
        ->assertSessionHasErrors('unit_id');

    expect(Tenancy::count())->toBe(1);
});

test('cannot use another landlord unit or tenant', function () {
    $foreignUnit = Unit::factory()->create();
    $foreignTenant = User::factory()->tenant()->create();

    $this->actingAs($this->landlord)
        ->post(route('tenancies.store'), tenancyPayload(['unit_id' => $foreignUnit->id, 'tenant_id' => $foreignTenant->id]))
        ->assertSessionHasErrors(['unit_id', 'tenant_id']);
});

test('ending a tenancy frees the unit and records the deposit', function () {
    $this->actingAs($this->landlord)->post(route('tenancies.store'), tenancyPayload());
    $tenancy = Tenancy::first();

    $this->actingAs($this->landlord)->post(route('tenancies.end', $tenancy), [
        'end_date' => '2027-01-31', 'deposit_status' => 'forfeited',
    ])->assertSessionHasNoErrors();

    expect($tenancy->fresh())
        ->status->toBe(TenancyStatus::Ended)
        ->deposit_status->toBe(DepositStatus::Forfeited)
        ->and($this->unit->fresh()->status)->toBe(UnitStatus::Vacant);
});

test('an ended tenancy cannot be ended again', function () {
    $tenancy = Tenancy::factory()->ended()->for($this->unit)->for($this->tenant, 'tenant')->create();

    $this->actingAs($this->landlord)->post(route('tenancies.end', $tenancy), [
        'end_date' => '2027-01-31', 'deposit_status' => 'refunded',
    ])->assertForbidden();
});

test('agreement pdf downloads for landlord and tenant only', function () {
    $tenancy = Tenancy::factory()->for($this->unit)->for($this->tenant, 'tenant')->create();

    $this->actingAs($this->landlord)->get(route('tenancies.agreement', $tenancy))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($this->tenant)->get(route('tenancies.agreement', $tenancy))->assertOk();
    $this->actingAs(User::factory()->tenant()->create())->get(route('tenancies.agreement', $tenancy))->assertForbidden();
});

test('create page lists only vacant units and free tenants', function () {
    Unit::factory()->for($this->property)->occupied()->create();

    $this->actingAs($this->landlord)->get(route('tenancies.create'))
        ->assertInertia(fn (Assert $p) => $p
            ->component('tenancies/create')
            ->has('tenants', 1)
            ->has('properties.0.units', 1));
});
