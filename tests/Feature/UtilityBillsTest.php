<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityBill;
use App\Models\UtilityMeter;
use App\Services\InvoiceService;
use App\Services\Utilities\UtilityBillService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10'));
    $this->property = Property::factory()->create();
    $this->landlord = $this->property->owner;
    $this->units = Unit::factory()->count(3)->sequence(['code' => 'R1'], ['code' => 'R2'], ['code' => 'R3'])
        ->create(['property_id' => $this->property->id]);
});

/** Rent a unit from Sep 2026 and give it an open October invoice. */
function rentOut(Unit $unit): Tenancy
{
    $tenancy = Tenancy::factory()->create([
        'unit_id' => $unit->id,
        'start_date' => '2026-09-01',
        'end_date' => '2027-08-31',
        'monthly_rent' => 400,
    ]);
    app(InvoiceService::class)->generateFor($tenancy, CarbonImmutable::parse('2026-10-01'));

    return $tenancy;
}

function meterFor(Property $property, iterable $units, string $label = 'TNB - Floor 1'): UtilityMeter
{
    $meter = $property->meters()->create(['type' => 'electricity', 'label' => $label]);
    $meter->units()->sync(collect($units)->pluck('id'));

    return $meter;
}

function octoberInvoice(Tenancy $tenancy): Invoice
{
    return $tenancy->invoices()->whereDate('period', '2026-10-01')->firstOrFail();
}

test('landlord adds a meter for chosen units of their own property', function () {
    $this->actingAs($this->landlord)
        ->post(route('meters.store', $this->property), [
            'type' => 'electricity', 'label' => 'TNB - Floor 1', 'account_no' => '2200123',
            'unit_ids' => [$this->units[0]->id, $this->units[1]->id],
        ])
        ->assertSessionHasNoErrors();

    $meter = UtilityMeter::firstOrFail();
    expect($meter->units()->pluck('code')->sort()->values()->all())->toBe(['R1', 'R2']);
});

test('meter units must belong to the same property and another landlord is blocked', function () {
    $foreign = Unit::factory()->create();

    $this->actingAs($this->landlord)
        ->post(route('meters.store', $this->property), ['type' => 'water', 'label' => 'SAMB', 'unit_ids' => [$foreign->id]])
        ->assertSessionHasErrors('unit_ids.0');

    $this->actingAs(User::factory()->landlord()->create())
        ->post(route('meters.store', $this->property), ['type' => 'water', 'label' => 'SAMB', 'unit_ids' => [$this->units[0]->id]])
        ->assertForbidden();
});

test('bill is split equally between occupied units only', function () {
    $a = rentOut($this->units[0]);
    $b = rentOut($this->units[1]); // R3 stays empty
    $meter = meterFor($this->property, $this->units);

    $this->actingAs($this->landlord)
        ->post(route('meters.bills.store', $meter), ['period' => '2026-09', 'amount' => 90])
        ->assertSessionHasNoErrors();

    expect(octoberInvoice($a)->total)->toBe('445.00')
        ->and(octoberInvoice($b)->total)->toBe('445.00')
        ->and(octoberInvoice($a)->items()->pluck('description'))->toContain('TNB - Floor 1 (Sep 2026)');
});

test('odd cents go to the first shares so the total always matches', function () {
    expect(UtilityBillService::split(100, 3))->toBe(['33.34', '33.33', '33.33'])
        ->and(UtilityBillService::split(0.05, 2))->toBe(['0.03', '0.02'])
        ->and(UtilityBillService::split(50, 0))->toBe([]);
});

test('nobody is charged when every unit on the meter is empty', function () {
    $meter = meterFor($this->property, $this->units);

    $this->actingAs($this->landlord)
        ->post(route('meters.bills.store', $meter), ['period' => '2026-09', 'amount' => 60]);

    expect(UtilityBill::firstOrFail()->shares()->count())->toBe(0);
});

test('a tenant who moved in after the bill month is not charged', function () {
    $old = rentOut($this->units[0]);
    $new = Tenancy::factory()->create(['unit_id' => $this->units[1]->id, 'start_date' => '2026-10-01', 'end_date' => '2027-09-30']);
    $meter = meterFor($this->property, [$this->units[0], $this->units[1]]);

    app(UtilityBillService::class)->record($meter, CarbonImmutable::parse('2026-09-01'), 80);

    expect(octoberInvoice($old)->total)->toBe('480.00')
        ->and(UtilityBill::firstOrFail()->shares()->where('tenancy_id', $new->id)->exists())->toBeFalse();
});

test('share waits when the invoice is already paid and lands on the next invoice', function () {
    $tenancy = rentOut($this->units[0]);
    $october = octoberInvoice($tenancy);
    app(InvoiceService::class)->recordPayment($october, 400, PaymentMethod::Cash);
    $meter = meterFor($this->property, [$this->units[0]]);

    app(UtilityBillService::class)->record($meter, CarbonImmutable::parse('2026-09-01'), 55.5);

    expect($october->fresh()->total)->toBe('400.00')
        ->and(UtilityBill::firstOrFail()->shares()->first()->invoice_item_id)->toBeNull();

    $november = app(InvoiceService::class)->generateFor($tenancy, CarbonImmutable::parse('2026-11-01'));

    expect($november->fresh()->total)->toBe('455.50');
});

test('voiding an invoice sends its shares to the next invoice', function () {
    $tenancy = rentOut($this->units[0]);
    $meter = meterFor($this->property, [$this->units[0]]);
    app(UtilityBillService::class)->record($meter, CarbonImmutable::parse('2026-09-01'), 30);

    app(InvoiceService::class)->void(octoberInvoice($tenancy));
    $november = app(InvoiceService::class)->generateFor($tenancy, CarbonImmutable::parse('2026-11-01'));

    expect($november->fresh()->total)->toBe('430.00');
});

test('one bill per meter per month', function () {
    rentOut($this->units[0]);
    $meter = meterFor($this->property, [$this->units[0]]);

    $this->actingAs($this->landlord)->post(route('meters.bills.store', $meter), ['period' => '2026-09', 'amount' => 30]);
    $this->actingAs($this->landlord)
        ->post(route('meters.bills.store', $meter), ['period' => '2026-09', 'amount' => 30])
        ->assertSessionHasErrors('period');
});

test('removing a bill takes its lines off unpaid invoices, but not once paid', function () {
    $a = rentOut($this->units[0]);
    $b = rentOut($this->units[1]);
    $meter = meterFor($this->property, [$this->units[0], $this->units[1]]);
    $bill = app(UtilityBillService::class)->record($meter, CarbonImmutable::parse('2026-09-01'), 50);

    $this->actingAs($this->landlord)->delete(route('utility-bills.destroy', $bill))->assertSessionHasNoErrors();

    expect(octoberInvoice($a)->total)->toBe('400.00')
        ->and(UtilityBill::count())->toBe(0);

    $bill = app(UtilityBillService::class)->record($meter, CarbonImmutable::parse('2026-09-01'), 50);
    app(InvoiceService::class)->recordPayment(octoberInvoice($b), 425, PaymentMethod::Cash);

    $this->actingAs($this->landlord)->delete(route('utility-bills.destroy', $bill))->assertSessionHasErrors('bill');
    expect(octoberInvoice($b)->status)->toBe(InvoiceStatus::Paid);
});

test('a share line cannot be deleted from the invoice page', function () {
    $tenancy = rentOut($this->units[0]);
    $meter = meterFor($this->property, [$this->units[0]]);
    app(UtilityBillService::class)->record($meter, CarbonImmutable::parse('2026-09-01'), 30);
    $invoice = octoberInvoice($tenancy);
    $line = $invoice->items()->where('description', 'like', 'TNB%')->firstOrFail();

    $this->actingAs($this->landlord)->delete(route('invoices.items.destroy', [$invoice, $line]));

    expect($invoice->fresh()->total)->toBe('430.00');
});

test('bill photo is private and shown to the charged tenant only', function () {
    Storage::fake('local');
    $tenancy = rentOut($this->units[0]);
    $meter = meterFor($this->property, [$this->units[0]]);

    $this->actingAs($this->landlord)->post(route('meters.bills.store', $meter), [
        'period' => '2026-09', 'amount' => 30, 'bill' => UploadedFile::fake()->image('tnb.jpg'),
    ]);

    $file = octoberInvoice($tenancy)->attachments()->firstOrFail();
    Storage::disk('local')->assertExists($file->path);

    $this->actingAs($tenancy->tenant)->get(route('attachments.show', $file))->assertOk();
    $this->actingAs(User::factory()->tenant()->create())->get(route('attachments.show', $file))->assertForbidden();
});

test('AI scan pre-fills the bill and saving uses the scanned file', function () {
    Storage::fake('local');
    config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'steps' => [['content' => [['type' => 'text', 'text' => json_encode([
            'provider' => 'TNB', 'type' => 'electricity', 'account_no' => '2200', 'period' => '2026-09',
            'amount' => 120.6, 'usage' => 400, 'usage_unit' => 'kWh', 'due_date' => '2026-10-20',
        ])]]]],
    ])]);
    $tenancy = rentOut($this->units[0]);
    $meter = meterFor($this->property, [$this->units[0]]);

    $this->actingAs($this->landlord)
        ->post(route('meters.scan', $meter), ['bill' => UploadedFile::fake()->image('tnb.jpg')])
        ->assertSessionHas("meter_scan.{$meter->id}");

    $this->actingAs($this->landlord)->get(route('properties.show', $this->property))
        ->assertInertia(fn (Assert $page) => $page
            ->where("pendingScans.{$meter->id}.amount", 120.6)
            ->missing("pendingScans.{$meter->id}.file_path"));

    $this->actingAs($this->landlord)
        ->post(route('meters.bills.store', $meter), ['period' => '2026-09', 'amount' => 120.6, 'use_scan' => true])
        ->assertSessionMissing("meter_scan.{$meter->id}");

    expect(UtilityBill::firstOrFail()->attachments()->count())->toBe(1)
        ->and(octoberInvoice($tenancy)->total)->toBe('520.60');
});

test('property page lists meters with bill shares', function () {
    rentOut($this->units[0]);
    $meter = meterFor($this->property, [$this->units[0]]);
    app(UtilityBillService::class)->record($meter, CarbonImmutable::parse('2026-09-01'), 30);

    $this->actingAs($this->landlord)->get(route('properties.show', $this->property))
        ->assertInertia(fn (Assert $page) => $page
            ->has('meters', 1)
            ->where('meters.0.label', 'TNB - Floor 1')
            ->where('meters.0.bills.0.period', 'Sep 2026')
            ->where('meters.0.bills.0.shares.0.state', 'billed'));
});
