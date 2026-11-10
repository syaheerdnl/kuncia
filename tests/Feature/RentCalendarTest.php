<?php

use App\Enums\PaymentMethod;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Services\InvoiceService;
use App\Services\Reports\RentCalendar;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo('2026-10-10');
    $this->property = Property::factory()->create();
    $this->unit = Unit::factory()->create(['property_id' => $this->property->id, 'code' => 'A']);
    $this->invoices = app(InvoiceService::class);
    $this->calendar = app(RentCalendar::class);

    $this->rent = fn (array $extra = []) => Tenancy::factory()->create(array_merge([
        'unit_id' => $this->unit->id,
        'start_date' => '2026-07-01',
        'end_date' => null,
        'monthly_rent' => 850,
        'due_day' => 5,
    ], $extra));

    $this->month = fn (string $ym) => CarbonImmutable::parse($ym.'-01');
});

test('each month from move-in to now has a status, and a month with no invoice is missing', function () {
    $tenancy = ($this->rent)();

    $july = $this->invoices->generateFor($tenancy, ($this->month)('2026-07'));
    $this->invoices->recordPayment($july, 850, PaymentMethod::Transfer, null, CarbonImmutable::parse('2026-07-06'));
    $this->invoices->generateFor($tenancy, ($this->month)('2026-08'));
    $this->invoices->markOverdue(CarbonImmutable::today()); // August (unpaid, due 5 Aug) turns overdue
    $september = $this->invoices->generateFor($tenancy, ($this->month)('2026-09'));
    $this->invoices->void($september);
    // October has no invoice at all.

    $result = $this->calendar->for($this->property, CarbonImmutable::today());

    expect($result)->toHaveCount(1)
        ->and($result[0])->toMatchArray(['id' => $tenancy->id, 'rent' => '850.00'])
        ->and(array_column($result[0]['months'], 'status'))->toBe(['paid', 'overdue', 'void', 'missing'])
        ->and(array_column($result[0]['months'], 'month'))->toBe(['2026-07', '2026-08', '2026-09', '2026-10'])
        ->and($result[0]['months'][0]['invoice_id'])->toBe($july->id)
        ->and($result[0]['months'][3]['invoice_id'])->toBeNull()
        // Only a month that was never invoiced is "missing": a voided one is not.
        ->and($result[0]['missing'])->toBe([['month' => '2026-10', 'label' => 'October 2026']]);
});

test('months before the move-in and after this month are not listed', function () {
    ($this->rent)(['start_date' => '2026-09-15']);

    $months = $this->calendar->for($this->property, CarbonImmutable::today())[0]['months'];

    expect(array_column($months, 'month'))->toBe(['2026-09', '2026-10']);
});

test('a tenancy that has ended stops at its end month, and only active tenancies are listed', function () {
    ($this->rent)(['end_date' => '2026-08-31']);
    $ended = ($this->rent)(['status' => 'ended', 'end_date' => '2026-08-31']);

    $result = $this->calendar->for($this->property, CarbonImmutable::today());

    expect($result)->toHaveCount(1)
        ->and(array_column($result[0]['months'], 'month'))->toBe(['2026-07', '2026-08'])
        ->and(array_column($result, 'id'))->not->toContain($ended->id);
});

test('other properties are left out and a property with no tenancy is empty', function () {
    Tenancy::factory()->create(['start_date' => '2026-07-01', 'end_date' => null]);

    expect($this->calendar->for($this->property, CarbonImmutable::today()))->toBe([]);
});
