<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\InvoiceChargeController;
use App\Models\Tenancy;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $tenancy = Tenancy::factory()->create(['start_date' => '2026-09-01', 'monthly_rent' => 450]);
    $this->invoice = app(InvoiceService::class)->generateFor($tenancy, CarbonImmutable::parse('2026-10-01'));
    $this->landlord = $tenancy->unit->property->owner;
    $this->tenant = $tenancy->tenant;
    config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test']);
});

function geminiReturns(array $fields): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'steps' => [['content' => [['type' => 'text', 'text' => json_encode($fields)]]]],
        ]),
    ]);
}

test('landlord adds and removes a manual charge', function () {
    $this->actingAs($this->landlord)
        ->post(route('invoices.items.store', $this->invoice), ['description' => 'Electricity (TNB) Sep 2026', 'amount' => 82.40])
        ->assertSessionHasNoErrors();

    expect($this->invoice->fresh()->total)->toBe('532.40');

    $item = $this->invoice->items()->where('description', 'like', 'Electricity%')->first();
    $this->actingAs($this->landlord)->delete(route('invoices.items.destroy', [$this->invoice, $item]));

    expect($this->invoice->fresh()->total)->toBe('450.00');
});

test('charges are locked once paid or partly paid, and tenants cannot add', function () {
    $this->actingAs($this->tenant)
        ->post(route('invoices.items.store', $this->invoice), ['description' => 'X', 'amount' => 1])
        ->assertForbidden();

    app(InvoiceService::class)->recordPayment($this->invoice, 100, PaymentMethod::Cash);
    $this->actingAs($this->landlord)
        ->post(route('invoices.items.store', $this->invoice), ['description' => 'X', 'amount' => 1])
        ->assertForbidden();
});

test('scanning a bill stores it privately and keeps the AI result for confirmation', function () {
    Storage::fake('local');
    geminiReturns([
        'provider' => 'TNB', 'type' => 'electricity', 'account_no' => '220012345678', 'period' => '2026-09',
        'amount' => 82.4, 'usage' => 312, 'usage_unit' => 'kWh', 'due_date' => '2026-10-20',
    ]);

    $this->actingAs($this->landlord)
        ->post(route('invoices.scan', $this->invoice), ['bill' => UploadedFile::fake()->image('tnb.jpg')])
        ->assertSessionHas(InvoiceChargeController::scanKey($this->invoice));

    Storage::disk('local')->assertExists($this->invoice->attachments()->first()->path);
    expect($this->invoice->fresh()->total)->toBe('450.00'); // nothing charged until confirmed

    $this->actingAs($this->landlord)->get(route('invoices.show', $this->invoice))
        ->assertInertia(fn (Assert $p) => $p
            ->where('pendingScan.amount', 82.4)
            ->where('pendingScan.suggested_description', 'Electricity (TNB) Sep 2026')
            ->has('invoice.bills', 1));

    Http::assertSent(fn ($req) => $req->hasHeader('x-goog-api-key', 'test-key')
        && $req['model'] === 'gemini-test'
        && $req['input'][0]['type'] === 'image');
});

test('unreadable bill falls back to manual entry', function () {
    Storage::fake('local');
    geminiReturns(['provider' => null, 'amount' => null]);

    $this->actingAs($this->landlord)
        ->post(route('invoices.scan', $this->invoice), ['bill' => UploadedFile::fake()->image('blurry.jpg')])
        ->assertSessionMissing(InvoiceChargeController::scanKey($this->invoice));
});

test('AI service error does not break the page', function () {
    Storage::fake('local');
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429)]);

    $this->actingAs($this->landlord)
        ->post(route('invoices.scan', $this->invoice), ['bill' => UploadedFile::fake()->image('tnb.jpg')])
        ->assertRedirect()
        ->assertSessionMissing(InvoiceChargeController::scanKey($this->invoice));
});

test('tenant can see the attached bill as proof', function () {
    Storage::fake('local');
    geminiReturns(['amount' => 30, 'type' => 'water', 'provider' => 'SAMB', 'period' => '2026-09']);
    $this->actingAs($this->landlord)->post(route('invoices.scan', $this->invoice), ['bill' => UploadedFile::fake()->image('samb.png')]);

    $bill = $this->invoice->attachments()->first();
    $this->actingAs($this->tenant)->get(route('attachments.show', $bill))->assertOk();
    $this->actingAs($this->tenant)->get(route('my.invoices.show', $this->invoice))
        ->assertInertia(fn (Assert $p) => $p->has('invoice.bills', 1));
});

test('confirming the scan adds the charge and clears it', function () {
    session([InvoiceChargeController::scanKey($this->invoice) => ['amount' => 30]]);

    $this->actingAs($this->landlord)
        ->post(route('invoices.items.store', $this->invoice), ['description' => 'Water (SAMB) Sep 2026', 'amount' => 30])
        ->assertSessionMissing(InvoiceChargeController::scanKey($this->invoice));

    expect($this->invoice->fresh())->total->toBe('480.00')->status->toBe(InvoiceStatus::Unpaid);
});
