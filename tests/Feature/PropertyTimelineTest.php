<?php

use App\Enums\DepositStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TenancyStatus;
use App\Enums\TransactionType;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\TenancyTransaction;
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

    $this->rent = fn (string $start, array $extra = []) => Tenancy::factory()->create(array_merge([
        'unit_id' => $this->unit->id,
        'start_date' => $start,
        'end_date' => null,
        'monthly_rent' => 400,
        'deposit_amount' => 800,
        'due_day' => 5,
    ], $extra));
});

test('events run newest first: payments, then the move-in', function () {
    $tenancy = ($this->rent)('2026-07-01');
    $july = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-07-01'));
    $august = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-08-01'));

    $this->invoices->recordPayment($july, 400, PaymentMethod::Transfer, 'DuitNow 1', CarbonImmutable::parse('2026-07-06'));
    // August is paid late, in September: the event sits on the day the money came in.
    $this->invoices->recordPayment($august, 400, PaymentMethod::Cash, null, CarbonImmutable::parse('2026-09-01'));

    $events = $this->timeline->for($this->property);

    expect(array_column($events, 'type'))->toBe(['payment', 'payment', 'tenancy_start'])
        ->and(array_column($events, 'date'))->toBe(['2026-09-01', '2026-07-06', '2026-07-01'])
        ->and($events[0])->toMatchArray([
            'amount' => '400.00', 'method' => 'Cash', 'period' => 'Aug 2026', 'unit' => 'A',
            'tenant' => $tenancy->tenant->name,
        ])
        ->and($events[1])->toMatchArray(['method' => 'Transfer', 'reference' => 'DuitNow 1', 'period' => 'Jul 2026'])
        ->and($events[2])->toMatchArray([
            'amount' => '400.00', 'deposit' => '800.00', 'tenant' => $tenancy->tenant->name,
            'tenant_id' => $tenancy->tenant_id, // so the banner can link to the tenant's page
        ]);
});

test('a payment carries its invoice for the popup: lines, total and every payment on it', function () {
    $tenancy = ($this->rent)('2026-08-01');
    $invoice = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-08-01'));
    $this->travelTo('2026-08-03'); // the extra charge is added before the payments below
    $invoice->items()->create(['description' => 'Indah Water', 'amount' => 50]);
    $invoice->recalculateTotal();

    $this->invoices->recordPayment($invoice, 200, PaymentMethod::Cash, 'part 1', CarbonImmutable::parse('2026-08-05'));
    $this->invoices->recordPayment($invoice->fresh(), 250, PaymentMethod::Transfer, null, CarbonImmutable::parse('2026-08-20'));
    // A payment that never went through must not show up as money received.
    $invoice->payments()->create(['amount' => 9, 'method' => PaymentMethod::ToyyibPay, 'status' => PaymentStatus::Failed]);

    $events = $this->timeline->for($this->property);
    $popup = $events[0]['invoice'];

    expect($events[0]['type'])->toBe('payment')
        ->and($popup)->toMatchArray([
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'period' => 'August 2026',
            'status' => 'paid',
            'due_date' => '2026-08-05',
            'total' => '450.00',
        ])
        ->and(array_column($popup['items'], 'description'))->toBe(['Monthly rent - August 2026', 'Indah Water'])
        ->and(array_column($popup['payments'], 'amount'))->toBe(['200.00', '250.00'])
        // Both payments point at the same invoice.
        ->and($events[1]['invoice']['id'])->toBe($invoice->id);
});

test('late fees, utility bill shares and extra charges are events dated the day they were added', function () {
    $this->travelTo('2026-08-01');
    $tenancy = ($this->rent)('2026-08-01');
    $invoice = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-08-01'));

    $this->travelTo('2026-08-10');
    $this->invoices->markOverdue(CarbonImmutable::today()); // adds the RM20 late fee

    $this->travelTo('2026-08-12');
    $meter = $this->property->meters()->create(['type' => 'electricity', 'label' => 'TNB']);
    $meter->units()->sync([$this->unit->id]);
    app(UtilityBillService::class)->record($meter, CarbonImmutable::parse('2026-08-01'), 90);

    $this->travelTo('2026-08-15');
    $invoice->items()->create(['description' => 'Indah Water', 'amount' => 50]);

    $events = $this->timeline->for($this->property);

    expect(array_column($events, 'type'))->toBe(['extra_charge', 'utility_charge', 'late_fee', 'tenancy_start'])
        ->and(array_column($events, 'date'))->toBe(['2026-08-15', '2026-08-12', '2026-08-10', '2026-08-01'])
        ->and($events[0])->toMatchArray(['amount' => '50.00', 'description' => 'Indah Water', 'period' => 'Aug 2026'])
        ->and($events[1])->toMatchArray(['amount' => '90.00', 'description' => 'TNB (Aug 2026)'])
        ->and($events[2])->toMatchArray(['amount' => '20.00', 'description' => 'Late payment fee'])
        // Each charge carries its invoice so it can open the same popup as a payment.
        ->and($events[2]['invoice']['id'])->toBe($invoice->id)
        ->and(array_column($events[2]['invoice']['items'], 'description'))
        ->toContain('Late payment fee', 'TNB (Aug 2026)', 'Indah Water');
});

test('the rent line is not a charge event, and a voided invoice has no charges', function () {
    $this->travelTo('2026-08-01');
    $tenancy = ($this->rent)('2026-08-01');
    $invoice = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-08-01'));

    expect(array_column($this->timeline->for($this->property), 'type'))->toBe(['tenancy_start']);

    $invoice->items()->create(['description' => 'Indah Water', 'amount' => 50]);
    $this->invoices->void($invoice);

    expect(array_column($this->timeline->for($this->property), 'type'))->toBe(['tenancy_start']);
});

test('deposits received and money paid to the tenant sit on the timeline', function () {
    $tenancy = ($this->rent)('2026-07-01');

    TenancyTransaction::create([
        'tenancy_id' => $tenancy->id, 'type' => TransactionType::DepositReceived,
        'amount' => 400, 'occurred_on' => '2026-06-18', 'note' => 'deposit',
    ]);
    TenancyTransaction::create([
        'tenancy_id' => $tenancy->id, 'type' => TransactionType::PaidToTenant,
        'amount' => 4500, 'occurred_on' => '2026-11-02', 'note' => 'roof repair',
    ]);

    $events = $this->timeline->for($this->property);

    expect(array_column($events, 'type'))->toBe(['paid_to_tenant', 'tenancy_start', 'deposit_received'])
        ->and($events[0])->toMatchArray([
            'date' => '2026-11-02', 'amount' => '4500.00', 'note' => 'roof repair', 'tenant' => $tenancy->tenant->name,
        ])
        // The deposit came in before the tenant moved in.
        ->and($events[2])->toMatchArray(['date' => '2026-06-18', 'amount' => '400.00', 'note' => 'deposit']);
});

test('other properties transactions are left out', function () {
    $elsewhere = Tenancy::factory()->create(['start_date' => '2026-07-01', 'end_date' => null]);
    TenancyTransaction::create([
        'tenancy_id' => $elsewhere->id, 'type' => TransactionType::DepositReceived,
        'amount' => 100, 'occurred_on' => '2026-06-18',
    ]);

    expect($this->timeline->for($this->property))->toBe([]);
});

test('a finished tenancy adds a move-out with the deposit outcome', function () {
    $tenancy = ($this->rent)('2026-01-01', [
        'status' => TenancyStatus::Ended,
        'end_date' => '2026-06-30',
        'deposit_status' => DepositStatus::Refunded,
    ]);

    $events = $this->timeline->for($this->property);

    expect(array_column($events, 'type'))->toBe(['tenancy_end', 'tenancy_start'])
        ->and($events[0])->toMatchArray([
            'date' => '2026-06-30', 'deposit_status' => 'Refunded', 'tenant' => $tenancy->tenant->name,
            'tenant_id' => $tenancy->tenant_id,
        ]);
});

test('a running tenancy with a fixed end date has no move-out yet', function () {
    ($this->rent)('2026-01-01', ['end_date' => '2099-12-31']);

    expect(array_column($this->timeline->for($this->property), 'type'))->toBe(['tenancy_start']);
});

test('on the same day the move-out comes before payments, and the move-in last', function () {
    $tenancy = ($this->rent)('2026-07-01', ['status' => TenancyStatus::Ended, 'end_date' => '2026-07-01']);
    $invoice = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-07-01'));
    $this->invoices->recordPayment($invoice, 400, PaymentMethod::Cash, null, CarbonImmutable::parse('2026-07-01'));

    expect(array_column($this->timeline->for($this->property), 'type'))
        ->toBe(['tenancy_end', 'payment', 'tenancy_start']);
});

test('pending and failed payments, and other properties, are left out', function () {
    $tenancy = ($this->rent)('2026-07-01');
    $invoice = $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-07-01'));
    $invoice->payments()->create(['amount' => 400, 'method' => PaymentMethod::ToyyibPay, 'status' => PaymentStatus::Pending]);
    $invoice->payments()->create(['amount' => 400, 'method' => PaymentMethod::ToyyibPay, 'status' => PaymentStatus::Failed]);

    $elsewhere = Tenancy::factory()->create(['start_date' => '2026-07-01', 'end_date' => null]);
    $this->invoices->recordPayment(
        $this->invoices->generateFor($elsewhere, CarbonImmutable::parse('2026-07-01')),
        400, PaymentMethod::Cash, null, CarbonImmutable::parse('2026-07-02'),
    );

    expect(array_column($this->timeline->for($this->property), 'type'))->toBe(['tenancy_start']);
});

test('a property with no history has an empty timeline', function () {
    expect($this->timeline->for($this->property))->toBe([]);
});

test('the page carries the events for the owner only', function () {
    $tenancy = ($this->rent)('2026-07-01');
    $this->invoices->recordPayment(
        $this->invoices->generateFor($tenancy, CarbonImmutable::parse('2026-07-01')),
        400, PaymentMethod::Transfer, null, CarbonImmutable::parse('2026-07-06'),
    );

    $this->actingAs($this->property->owner)
        ->get(route('properties.show', $this->property))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('properties/show')
            ->has('timeline', 2)
            ->where('timeline.0.type', 'payment')
            ->where('timeline.1.type', 'tenancy_start')
        );

    $this->actingAs(User::factory()->landlord()->create())
        ->get(route('properties.show', $this->property))
        ->assertForbidden();
});
