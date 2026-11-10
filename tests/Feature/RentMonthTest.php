<?php

use App\Enums\InvoiceStatus;
use App\Enums\TenancyStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-10');

    $this->property = Property::factory()->create();
    $this->landlord = $this->property->owner;
    $this->unit = Unit::factory()->create(['property_id' => $this->property->id, 'code' => 'A']);
    $this->tenancy = Tenancy::factory()->create([
        'unit_id' => $this->unit->id,
        'start_date' => '2026-07-01',
        'end_date' => null,
        'monthly_rent' => 850,
        'due_day' => 5,
    ]);

    $this->valid = ['tenancy_id' => $this->tenancy->id, 'month' => '2026-10'];
    $this->make = fn (array $data, ?User $as = null) => $this->actingAs($as ?? $this->landlord)
        ->from('/back')
        ->post(route('rent-months.store', $this->property), $data);
});

test('a missed month gets its invoice at the tenancy rent, with no late fee waiting for it', function () {
    ($this->make)($this->valid)->assertRedirect('/back')->assertSessionHasNoErrors();

    $invoice = Invoice::firstOrFail();
    expect($invoice->tenancy_id)->toBe($this->tenancy->id)
        ->and($invoice->period->toDateString())->toBe('2026-10-01')
        ->and($invoice->due_date->toDateString())->toBe('2026-10-05')
        ->and($invoice->total)->toBe('850.00')
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->late_fee_waived)->toBeTrue();
});

test('the nightly job marks it overdue but does not add the late fee', function () {
    ($this->make)($this->valid)->assertSessionHasNoErrors();

    app(InvoiceService::class)->markOverdue(CarbonImmutable::today()); // due 5 Oct, today is 10 Oct

    $invoice = Invoice::firstOrFail();
    expect($invoice->status)->toBe(InvoiceStatus::Overdue)
        ->and($invoice->total)->toBe('850.00')
        ->and($invoice->items()->count())->toBe(1);
});

test('it can record the payment in the same step', function () {
    ($this->make)($this->valid + [
        'paid' => true, 'amount' => '850', 'paid_at' => '2026-10-08', 'method' => 'cash', 'reference' => 'receipt 12',
    ])->assertSessionHasNoErrors();

    $invoice = Invoice::firstOrFail();
    $payment = Payment::firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_at->toDateString())->toBe('2026-10-08')
        ->and($payment->amount)->toBe('850.00')
        ->and($payment->method->value)->toBe('cash')
        ->and($payment->reference)->toBe('receipt 12');
});

test('paying more than the balance is refused and no invoice is left behind', function () {
    ($this->make)($this->valid + [
        'paid' => true, 'amount' => '900', 'paid_at' => '2026-10-08', 'method' => 'transfer',
    ])->assertSessionHasErrors('amount');

    expect(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0);
});

test('a month that already has an invoice cannot be made again', function () {
    ($this->make)($this->valid)->assertSessionHasNoErrors();
    ($this->make)($this->valid)->assertSessionHasErrors('month');

    expect(Invoice::count())->toBe(1);
});

test('only a month that has started and belongs to the tenancy can be picked', function (string $month) {
    ($this->make)(['month' => $month] + $this->valid)->assertSessionHasErrors('month');

    expect(Invoice::count())->toBe(0);
})->with([
    'in the future' => ['2026-11'],
    'before the tenant moved in' => ['2026-06'],
    'not a month' => ['October'],
]);

test('a month after the tenancy ended is refused', function () {
    $this->tenancy->update(['end_date' => '2026-08-31']);

    ($this->make)(['month' => '2026-09'] + $this->valid)->assertSessionHasErrors('month');
});

test('payment details are needed when it is marked paid', function (array $change, string $field) {
    ($this->make)($change + $this->valid + ['paid' => true, 'amount' => '850', 'paid_at' => '2026-10-08', 'method' => 'cash'])
        ->assertSessionHasErrors($field);

    expect(Invoice::count())->toBe(0);
})->with([
    'no amount' => [['amount' => null], 'amount'],
    'no date' => [['paid_at' => null], 'paid_at'],
    'future date' => [['paid_at' => '2099-01-01'], 'paid_at'],
    'online method' => [['method' => 'toyyibpay'], 'method'],
]);

test('only a current tenancy on this property can be used', function () {
    $elsewhere = Tenancy::factory()->create(['start_date' => '2026-07-01', 'end_date' => null]);
    $ended = Tenancy::factory()->create([
        'unit_id' => Unit::factory()->create(['property_id' => $this->property->id, 'code' => 'B'])->id,
        'start_date' => '2026-07-01', 'status' => TenancyStatus::Ended, 'end_date' => '2026-08-31',
    ]);

    ($this->make)(['tenancy_id' => $elsewhere->id] + $this->valid)->assertSessionHasErrors('tenancy_id');
    ($this->make)(['tenancy_id' => $ended->id] + $this->valid)->assertSessionHasErrors('tenancy_id');

    expect(Invoice::count())->toBe(0);
});

test('only the owning landlord can do this', function () {
    ($this->make)($this->valid, User::factory()->landlord()->create())->assertForbidden();
    ($this->make)($this->valid, User::factory()->tenant()->create())->assertForbidden();

    expect(Invoice::count())->toBe(0);
});

test('the property page lists the missing months for its owner', function () {
    $this->actingAs($this->landlord)
        ->get(route('properties.show', $this->property))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rentCalendar', 1)
            ->where('rentCalendar.0.id', $this->tenancy->id)
            ->has('rentCalendar.0.months', 4)
            ->has('rentCalendar.0.missing', 4)
            ->where('rentCalendar.0.missing.3.label', 'October 2026')
        );
});
