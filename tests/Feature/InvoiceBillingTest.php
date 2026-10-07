<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenancy = Tenancy::factory()->create([
        'start_date' => '2026-09-01',
        'end_date' => '2027-08-31',
        'monthly_rent' => 450,
        'due_day' => 7,
    ]);
    $this->landlord = $this->tenancy->unit->property->owner;
    $this->service = app(InvoiceService::class);
});

test('generate command bills every active tenancy once', function () {
    Tenancy::factory()->ended()->create(); // should be skipped

    $this->artisan('invoices:generate', ['--month' => '2026-10'])->assertSuccessful();
    $this->artisan('invoices:generate', ['--month' => '2026-10'])->assertSuccessful(); // re-run is safe

    $invoices = Invoice::all();
    expect($invoices)->toHaveCount(1);

    $invoice = $invoices->first();
    expect($invoice->invoice_no)->toBe('INV-202610-0001')
        ->and($invoice->total)->toBe('450.00')
        ->and($invoice->due_date->toDateString())->toBe('2026-10-07')
        ->and($invoice->items)->toHaveCount(1);
});

test('no invoice outside the tenancy dates', function () {
    expect($this->service->generateFor($this->tenancy, CarbonImmutable::parse('2026-08-01')))->toBeNull()
        ->and($this->service->generateFor($this->tenancy, CarbonImmutable::parse('2027-09-01')))->toBeNull();
});

test('overdue adds the late fee exactly once', function () {
    $invoice = $this->service->generateFor($this->tenancy, CarbonImmutable::parse('2026-10-01'));

    $this->travelTo('2026-10-08');
    $this->artisan('invoices:mark-overdue')->assertSuccessful();
    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Overdue)
        ->total->toBe('470.00')
        ->and($invoice->items()->count())->toBe(2);
});

test('not overdue on the due date itself', function () {
    $invoice = $this->service->generateFor($this->tenancy, CarbonImmutable::parse('2026-10-01'));

    $this->travelTo('2026-10-07 23:00');
    $this->artisan('invoices:mark-overdue');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

test('partial then full payment closes the invoice', function () {
    $invoice = $this->service->generateFor($this->tenancy, CarbonImmutable::parse('2026-10-01'));
    $url = route('invoices.payments.store', $invoice);

    $this->actingAs($this->landlord)->post($url, ['amount' => 200, 'method' => 'cash', 'paid_at' => now()->toDateString()])
        ->assertSessionHasNoErrors();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($this->service->outstanding($invoice))->toBe(250.0);

    // Cannot overpay
    $this->actingAs($this->landlord)->post($url, ['amount' => 300, 'method' => 'cash', 'paid_at' => now()->toDateString()])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->landlord)->post($url, ['amount' => 250, 'method' => 'transfer', 'reference' => 'DN123', 'paid_at' => now()->toDateString()]);
    expect($invoice->fresh())->status->toBe(InvoiceStatus::Paid)->paid_at->not->toBeNull();
});

test('paid invoice cannot take more payments and tenant cannot record payments', function () {
    $invoice = $this->service->generateFor($this->tenancy, CarbonImmutable::parse('2026-10-01'));

    $this->actingAs($this->tenancy->tenant)
        ->post(route('invoices.payments.store', $invoice), ['amount' => 450, 'method' => 'cash', 'paid_at' => now()->toDateString()])
        ->assertForbidden();

    $invoice->update(['status' => InvoiceStatus::Paid]);
    $this->actingAs($this->landlord)
        ->post(route('invoices.payments.store', $invoice), ['amount' => 1, 'method' => 'cash', 'paid_at' => now()->toDateString()])
        ->assertForbidden();
});

test('void only works without payments', function () {
    $invoice = $this->service->generateFor($this->tenancy, CarbonImmutable::parse('2026-10-01'));

    $this->actingAs($this->landlord)->post(route('invoices.void', $invoice));
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

test('landlord list is scoped, filtered and summarised', function () {
    $this->service->generateFor($this->tenancy, CarbonImmutable::parse('2026-09-01'))->update(['status' => InvoiceStatus::Paid]);
    $this->service->generateFor($this->tenancy, CarbonImmutable::parse('2026-10-01'));
    Invoice::factory()->create(); // another landlord's

    $this->actingAs($this->landlord)->get(route('invoices.index'))
        ->assertInertia(fn (Assert $p) => $p
            ->component('invoices/index')
            ->where('invoices.total', 2)
            ->where('summary.outstanding', fn ($v) => (float) $v === 450.0)
            ->where('summary.collected', fn ($v) => (float) $v === 450.0));

    $this->actingAs($this->landlord)->get(route('invoices.index', ['status' => 'paid']))
        ->assertInertia(fn (Assert $p) => $p->where('invoices.total', 1));
});

test('tenant sees own invoices and pdf; others are blocked', function () {
    $invoice = $this->service->generateFor($this->tenancy, CarbonImmutable::parse('2026-10-01'));
    $tenant = $this->tenancy->tenant;

    $this->actingAs($tenant)->get(route('my.invoices'))
        ->assertInertia(fn (Assert $p) => $p->component('my/invoices')->has('invoices', 1));
    $this->actingAs($tenant)->get(route('my.invoices.show', $invoice))->assertOk();
    $this->actingAs($tenant)->get(route('invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');

    $stranger = User::factory()->tenant()->create();
    $this->actingAs($stranger)->get(route('my.invoices.show', $invoice))->assertForbidden();
    $this->actingAs($stranger)->get(route('invoices.pdf', $invoice))->assertForbidden();
});
