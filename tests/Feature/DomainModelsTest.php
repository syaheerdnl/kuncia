<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Support\Carbon;

test('full rental chain can be created from factories', function () {
    $tenancy = Tenancy::factory()->create();
    $invoice = Invoice::factory()->for($tenancy)->create();
    InvoiceItem::factory()->for($invoice)->create(['amount' => 450]);
    InvoiceItem::factory()->for($invoice)->create(['description' => 'Electricity', 'amount' => 35.50]);
    Payment::factory()->for($invoice)->create();

    $invoice->recalculateTotal();

    $landlord = $tenancy->unit->property->owner;

    expect($landlord->role)->toBe(UserRole::Landlord)
        ->and($tenancy->tenant->role)->toBe(UserRole::Tenant)
        ->and($landlord->properties)->toHaveCount(1)
        ->and($tenancy->unit->activeTenancy->id)->toBe($tenancy->id)
        ->and($invoice->fresh()->total)->toBe('485.50')
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->payments)->toHaveCount(1);
});

test('invoice numbers run per month', function () {
    $period = Carbon::parse('2026-10-01');

    expect(Invoice::nextNumber($period))->toBe('INV-202610-0001');

    Invoice::factory()->create(['invoice_no' => 'INV-202610-0001', 'period' => $period]);

    expect(Invoice::nextNumber($period))->toBe('INV-202610-0002')
        ->and(Invoice::nextNumber(Carbon::parse('2026-11-01')))->toBe('INV-202611-0001');
});

test('maintenance request links tenant and assignee', function () {
    $tech = User::factory()->maintenance()->create();
    $request = MaintenanceRequest::factory()->create(['assigned_to' => $tech->id]);

    expect($request->assignee->isMaintenance())->toBeTrue()
        ->and($tech->assignedRequests)->toHaveCount(1);
});
