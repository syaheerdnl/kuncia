<?php

use App\Enums\TransactionType;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\TenancyTransaction;
use App\Models\Unit;
use App\Models\User;
use App\Support\GuestSandbox;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->property = Property::factory()->create();
    $this->landlord = $this->property->owner;
    $this->unit = Unit::factory()->create(['property_id' => $this->property->id]);
    $this->tenancy = Tenancy::factory()->create(['unit_id' => $this->unit->id, 'start_date' => '2026-01-01', 'end_date' => null]);

    $this->valid = [
        'tenancy_id' => $this->tenancy->id,
        'type' => 'deposit_received',
        'amount' => '850',
        'occurred_on' => '2026-01-02',
        'note' => 'First deposit',
    ];
    $this->record = fn (array $data, ?User $as = null) => $this->actingAs($as ?? $this->landlord)
        ->from('/back')
        ->post(route('transactions.store', $this->property), $data);
});

test('a landlord records a deposit and it shows on the timeline', function () {
    ($this->record)($this->valid)->assertRedirect('/back')->assertSessionHasNoErrors();

    $row = TenancyTransaction::firstOrFail();
    expect($row->tenancy_id)->toBe($this->tenancy->id)
        ->and($row->type)->toBe(TransactionType::DepositReceived)
        ->and($row->amount)->toBe('850.00')
        ->and($row->occurred_on->toDateString())->toBe('2026-01-02')
        ->and($row->note)->toBe('First deposit');

    $this->actingAs($this->landlord)
        ->get(route('properties.show', $this->property))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeline.0.type', 'deposit_received')
            ->where('timeline.0.id', $row->id)
            // The form's tenant list: everyone who rents or rented here.
            ->has('transactionTenancies', 1)
            ->where('transactionTenancies.0.id', $this->tenancy->id)
        );
});

test('a payout needs no note', function () {
    ($this->record)(['type' => 'paid_to_tenant', 'note' => null] + $this->valid)->assertSessionHasNoErrors();

    expect(TenancyTransaction::firstOrFail())
        ->type->toBe(TransactionType::PaidToTenant)
        ->note->toBeNull();
});

test('bad input is refused and nothing is saved', function (string $field, mixed $value) {
    ($this->record)([$field => $value] + $this->valid)->assertSessionHasErrors($field);

    expect(TenancyTransaction::count())->toBe(0);
})->with([
    'unknown kind' => ['type', 'gift'],
    'zero amount' => ['amount', '0'],
    'not a number' => ['amount', 'abc'],
    'huge amount' => ['amount', '99999999'],
    'future date' => ['occurred_on', '2099-01-01'],
    'wrong date format' => ['occurred_on', '02/01/2026'],
    'long note' => ['note', str_repeat('x', 151)],
    'no tenant' => ['tenancy_id', null],
]);

test('a tenancy from another property cannot be used', function () {
    $elsewhere = Tenancy::factory()->create();

    ($this->record)(['tenancy_id' => $elsewhere->id] + $this->valid)->assertSessionHasErrors('tenancy_id');

    expect(TenancyTransaction::count())->toBe(0);
});

test('the exact same entry cannot be recorded twice', function () {
    ($this->record)($this->valid)->assertSessionHasNoErrors();
    ($this->record)($this->valid)->assertSessionHasErrors('amount');

    expect(TenancyTransaction::count())->toBe(1);

    // A different amount on the same day is a different entry.
    ($this->record)(['amount' => '900'] + $this->valid)->assertSessionHasNoErrors();
    expect(TenancyTransaction::count())->toBe(2);
});

test('only the owning landlord can record', function () {
    $other = User::factory()->landlord()->create();
    ($this->record)($this->valid, $other)->assertForbidden();

    $tenant = User::factory()->tenant()->create();
    ($this->record)($this->valid, $tenant)->assertForbidden();

    expect(TenancyTransaction::count())->toBe(0);
});

test('an entry can be deleted by its landlord only', function () {
    $row = TenancyTransaction::create([
        'tenancy_id' => $this->tenancy->id, 'type' => TransactionType::PaidToTenant,
        'amount' => 4500, 'occurred_on' => '2026-02-02',
    ]);

    $this->actingAs(User::factory()->landlord()->create())
        ->delete(route('transactions.destroy', $row))->assertForbidden();
    expect(TenancyTransaction::count())->toBe(1);

    $this->actingAs($this->landlord)
        ->from('/back')->delete(route('transactions.destroy', $row))->assertRedirect('/back');
    expect(TenancyTransaction::count())->toBe(0);
});

test('the nightly guest reset still works when the guest recorded a deposit', function () {
    $guest = app(GuestSandbox::class)->reset();
    $tenancy = Tenancy::whereHas('unit.property', fn ($q) => $q->where('owner_id', $guest->id))->firstOrFail();

    TenancyTransaction::create([
        'tenancy_id' => $tenancy->id, 'type' => TransactionType::DepositReceived,
        'amount' => 500, 'occurred_on' => '2026-01-02',
    ]);

    app(GuestSandbox::class)->reset(); // would hit the foreign key before the fix

    expect(TenancyTransaction::count())->toBe(0);
});
