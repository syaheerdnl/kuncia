<?php

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\User;

test('registration creates a landlord', function () {
    $this->post(route('register.store'), [
        'name' => 'New Landlord',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'new@example.com')->first()->role)->toBe(UserRole::Landlord);
});

test('each role only reaches its own area', function (string $role, string $allowed, string $blocked) {
    $user = User::factory()->state(['role' => $role])->create();

    $this->actingAs($user)->get($allowed)->assertOk();
    $this->actingAs($user)->get($blocked)->assertForbidden();
})->with([
    'landlord' => ['landlord', '/properties', '/my/invoices'],
    'tenant' => ['tenant', '/my/invoices', '/properties'],
    'maintenance' => ['maintenance', '/tasks', '/invoices'],
]);

test('landlord cannot touch another landlord property', function () {
    $owner = User::factory()->landlord()->create();
    $other = User::factory()->landlord()->create();
    $property = Property::factory()->for($owner, 'owner')->create();

    expect($owner->can('update', $property))->toBeTrue()
        ->and($other->can('view', $property))->toBeFalse()
        ->and($other->can('delete', $property))->toBeFalse();
});

test('tenant sees only own invoices and can pay unpaid ones', function () {
    $invoice = Invoice::factory()->create();
    $tenant = $invoice->tenancy->tenant;
    $stranger = User::factory()->tenant()->create();
    $landlord = $invoice->tenancy->unit->property->owner;

    expect($tenant->can('view', $invoice))->toBeTrue()
        ->and($tenant->can('pay', $invoice))->toBeTrue()
        ->and($stranger->can('view', $invoice))->toBeFalse()
        ->and($landlord->can('update', $invoice))->toBeTrue()
        ->and($landlord->can('pay', $invoice))->toBeFalse();

    $paid = Invoice::factory()->paid()->for($invoice->tenancy)->create(['period' => now()->subMonth()->startOfMonth()]);
    expect($tenant->can('pay', $paid))->toBeFalse();
});

test('maintenance staff only see tickets assigned to them', function () {
    $tech = User::factory()->maintenance()->create();
    $otherTech = User::factory()->maintenance()->create();
    $ticket = MaintenanceRequest::factory()->create(['assigned_to' => $tech->id]);

    expect($tech->can('view', $ticket))->toBeTrue()
        ->and($tech->can('updateStatus', $ticket))->toBeTrue()
        ->and($tech->can('assign', $ticket))->toBeFalse()
        ->and($otherTech->can('view', $ticket))->toBeFalse();
});
