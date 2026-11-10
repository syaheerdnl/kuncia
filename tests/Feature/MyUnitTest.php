<?php

use App\Models\Tenancy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('tenant sees their active unit, terms and landlord', function () {
    $tenancy = Tenancy::factory()->create(['monthly_rent' => 450, 'due_day' => 7]);
    $landlord = $tenancy->unit->property->owner;

    $this->actingAs($tenancy->tenant)
        ->get(route('my.unit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('my/unit')
            ->where('tenancy.id', $tenancy->id)
            ->where('tenancy.monthly_rent', '450.00')
            ->where('tenancy.due_day', 7)
            ->where('tenancy.unit.code', $tenancy->unit->code)
            ->where('tenancy.property.name', $tenancy->unit->property->name)
            ->where('tenancy.landlord.email', $landlord->email)
        );
});

test('tenant without an active tenancy sees an empty state', function () {
    $tenant = User::factory()->tenant()->create();
    Tenancy::factory()->ended()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($tenant)
        ->get(route('my.unit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('my/unit')
            ->where('tenancy', null)
        );
});

test('tenant only ever sees their own tenancy', function () {
    $mine = Tenancy::factory()->create();
    $theirs = Tenancy::factory()->create();

    $this->actingAs($mine->tenant)
        ->get(route('my.unit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenancy.id', $mine->id)
            ->missing('tenancy.tenant_id')
        );

    expect($theirs->id)->not->toBe($mine->id);
});

test('only tenants can open my unit', function (string $role) {
    $user = User::factory()->state(['role' => $role])->create();

    $this->actingAs($user)->get(route('my.unit'))->assertForbidden();
})->with(['landlord', 'maintenance']);

test('guests are sent to login', function () {
    $this->get(route('my.unit'))->assertRedirect(route('login'));
});
