<?php

use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UnitStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;

/**
 * File shape (all names, emails and amounts below are made up):
 * {
 *   "landlord": "email of an existing landlord",
 *   "tenancies": [{
 *     "property": "exact property name", "unit": "exact unit code",
 *     "tenant": {"name": "...", "email": "...", "phone": "012-3456789"},
 *     "start_date": "2025-01-01", "monthly_rent": 850, "deposit": 1700, "due_day": 28,
 *     "payments": [{"period": "2025-01", "paid_at": "2025-01-25", "amount": 850,
 *                   "method": "transfer", "reference": "optional",
 *                   "extras": [{"description": "Indah Water", "amount": 234}]}]
 *   }]
 * }
 */
beforeEach(function () {
    $this->landlord = User::factory()->landlord()->create(['email' => 'landlord@example.test']);
    $this->property = Property::factory()->for($this->landlord, 'owner')->create(['name' => 'Test House']);
    $this->unit = Unit::factory()->for($this->property)->create(['code' => 'MAIN', 'status' => UnitStatus::Vacant]);

    $this->data = fn (array $overrides = []) => [
        'landlord' => 'landlord@example.test',
        'tenancies' => [array_merge([
            'property' => 'Test House',
            'unit' => 'MAIN',
            'tenant' => ['name' => 'Ali Test', 'email' => 'ali@example.test', 'phone' => '012-3456789'],
            'start_date' => '2025-01-01',
            'monthly_rent' => 850,
            'deposit' => 1700,
            'due_day' => 28,
            'payments' => [
                ['period' => '2025-01', 'paid_at' => '2025-01-25', 'amount' => 850],
                ['period' => '2025-02', 'paid_at' => '2025-02-27', 'amount' => 1084, 'method' => 'cash', 'reference' => 'Feb + water',
                    'extras' => [['description' => 'Indah Water', 'amount' => 234]]],
            ],
        ], $overrides)],
    ];
});

test('imports the tenant, tenancy and paid invoices', function () {
    $this->artisan('kuncia:import-history', ['file' => writeImport(($this->data)())])->assertSuccessful();

    $tenant = User::where('email', 'ali@example.test')->firstOrFail();
    $tenancy = Tenancy::where('tenant_id', $tenant->id)->firstOrFail();

    expect($tenant->landlord_id)->toBe($this->landlord->id)
        ->and($tenancy->deposit_amount)->toBe('1700.00')
        ->and($tenancy->deposit_status)->toBe(DepositStatus::Held)
        ->and($tenancy->due_day)->toBe(28)
        ->and($this->unit->fresh()->status)->toBe(UnitStatus::Occupied);

    $invoices = Invoice::orderBy('period')->get();
    expect($invoices)->toHaveCount(2)
        ->and($invoices->pluck('status')->unique()->all())->toBe([InvoiceStatus::Paid])
        ->and($invoices[0]->total)->toBe('850.00')
        ->and($invoices[1]->total)->toBe('1084.00')
        ->and($invoices[1]->items()->pluck('description')->all())->toContain('Indah Water')
        ->and($invoices[1]->paid_at->toDateString())->toBe('2025-02-27');

    $payment = Payment::where('invoice_id', $invoices[1]->id)->firstOrFail();
    expect($payment->method->value)->toBe('cash')
        ->and($payment->reference)->toBe('Feb + water');
});

test('running it twice creates nothing new', function () {
    $file = writeImport(($this->data)());

    $this->artisan('kuncia:import-history', ['file' => $file])->assertSuccessful();
    $this->artisan('kuncia:import-history', ['file' => $file])->assertSuccessful();

    expect(User::where('email', 'ali@example.test')->count())->toBe(1)
        ->and(Tenancy::count())->toBe(1)
        ->and(Invoice::count())->toBe(2)
        ->and(Payment::count())->toBe(2);
});

test('a dry run saves nothing', function () {
    $this->artisan('kuncia:import-history', ['file' => writeImport(($this->data)()), '--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();

    expect(User::where('email', 'ali@example.test')->exists())->toBeFalse()
        ->and(Tenancy::count())->toBe(0)
        ->and(Invoice::count())->toBe(0)
        ->and($this->unit->fresh()->status)->toBe(UnitStatus::Vacant);
});

test('a payment that does not match the invoice is rejected and nothing is saved', function () {
    $data = ($this->data)(['payments' => [
        ['period' => '2025-01', 'paid_at' => '2025-01-25', 'amount' => 850],
        ['period' => '2025-02', 'paid_at' => '2025-02-27', 'amount' => 1084], // no extras to explain the 234
    ]]);

    $this->artisan('kuncia:import-history', ['file' => writeImport($data)])
        ->expectsOutputToContain('does not match the invoice total')
        ->assertFailed();

    expect(Tenancy::count())->toBe(0)
        ->and(Invoice::count())->toBe(0)
        ->and(User::where('email', 'ali@example.test')->exists())->toBeFalse();
});

test('a file with TODO placeholders is refused', function () {
    $data = ($this->data)(['tenant' => ['name' => 'TODO', 'email' => 'ali@example.test', 'phone' => '012-3456789']]);

    $this->artisan('kuncia:import-history', ['file' => writeImport($data)])
        ->expectsOutputToContain('TODO placeholders')
        ->assertFailed();

    expect(Tenancy::count())->toBe(0);
});

test('an unknown property or unit is refused', function () {
    $this->artisan('kuncia:import-history', ['file' => writeImport(($this->data)(['unit' => 'NOPE']))])
        // The message also lists what does exist, so a wrong name or code is easy to fix.
        ->expectsOutputToContain('was not found for this landlord. Available: Test House [MAIN]')
        ->assertFailed();

    expect(Tenancy::count())->toBe(0);
});

test('an email that belongs to another account is refused', function () {
    User::factory()->landlord()->create(['email' => 'ali@example.test']);

    $this->artisan('kuncia:import-history', ['file' => writeImport(($this->data)())])
        ->expectsOutputToContain('already belongs to a different account')
        ->assertFailed();
});

test('imported months never get a late fee from the nightly job', function () {
    $this->artisan('kuncia:import-history', ['file' => writeImport(($this->data)())])->assertSuccessful();

    $this->travelTo('2026-01-01');
    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect(Invoice::where('status', InvoiceStatus::Overdue)->count())->toBe(0)
        ->and(Invoice::orderBy('period')->first()->total)->toBe('850.00');
});

/** @param array<string, mixed> $data */
function writeImport(array $data): string
{
    $path = tempnam(sys_get_temp_dir(), 'import-').'.json';
    file_put_contents($path, json_encode($data));

    return $path;
}
