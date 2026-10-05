<?php

use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function validProperty(array $overrides = []): array
{
    return [
        'name' => 'Hostel Test',
        'type' => 'hostel',
        'address' => '1, Jalan Ujian',
        'city' => 'Durian Tunggal',
        'state' => 'Melaka',
        'postcode' => '76100',
        ...$overrides,
    ];
}

test('landlord sees only own properties with occupancy counts', function () {
    $landlord = User::factory()->landlord()->create();
    $mine = Property::factory()->for($landlord, 'owner')->create();
    Unit::factory()->for($mine)->occupied()->create();
    Unit::factory()->for($mine)->create();
    Property::factory()->create(); // someone else's

    $this->actingAs($landlord)->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('properties/index')
            ->has('properties', 1)
            ->where('properties.0.units_count', 2)
            ->where('properties.0.occupied_count', 1));
});

test('landlord can create a property with a cover photo', function () {
    Storage::fake('public');
    $landlord = User::factory()->landlord()->create();

    $this->actingAs($landlord)
        ->post(route('properties.store'), validProperty(['cover_image' => UploadedFile::fake()->image('front.jpg')]))
        ->assertRedirect();

    $property = $landlord->properties()->first();
    expect($property->name)->toBe('Hostel Test');
    Storage::disk('public')->assertExists($property->cover_image);
});

test('property validation rejects bad postcode and state', function () {
    $landlord = User::factory()->landlord()->create();

    $this->actingAs($landlord)
        ->post(route('properties.store'), validProperty(['postcode' => '12', 'state' => 'Bangkok']))
        ->assertSessionHasErrors(['postcode', 'state']);
});

test('another landlord gets 403 on view, update and delete', function () {
    $property = Property::factory()->create();
    $intruder = User::factory()->landlord()->create();

    $this->actingAs($intruder)->get(route('properties.show', $property))->assertForbidden();
    $this->actingAs($intruder)->put(route('properties.update', $property), validProperty())->assertForbidden();
    $this->actingAs($intruder)->delete(route('properties.destroy', $property))->assertForbidden();
});

test('owner can update property', function () {
    $property = Property::factory()->create();

    $this->actingAs($property->owner)
        ->put(route('properties.update', $property), validProperty(['name' => 'Renamed']))
        ->assertRedirect(route('properties.show', $property));

    expect($property->fresh()->name)->toBe('Renamed');
});

test('property with tenancy history cannot be deleted', function () {
    $tenancy = Tenancy::factory()->create();
    $property = $tenancy->unit->property;

    $this->actingAs($property->owner)->delete(route('properties.destroy', $property));

    expect(Property::find($property->id))->not->toBeNull();
});

test('empty property can be deleted', function () {
    $property = Property::factory()->create();

    $this->actingAs($property->owner)
        ->delete(route('properties.destroy', $property))
        ->assertRedirect(route('properties.index'));

    expect(Property::find($property->id))->toBeNull();
});

test('units: add, duplicate code blocked, update, delete', function () {
    $property = Property::factory()->create();
    $owner = $property->owner;
    $unit = ['code' => 'A-101', 'type' => 'room', 'monthly_rent' => 350, 'deposit' => 700];

    $this->actingAs($owner)->post(route('properties.units.store', $property), $unit)->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('properties.units.store', $property), $unit)->assertSessionHasErrors('code');

    $created = $property->units()->first();
    expect($created->status)->toBe(UnitStatus::Vacant);

    $this->actingAs($owner)->put(route('units.update', $created), [...$unit, 'monthly_rent' => 400, 'status' => 'maintenance'])
        ->assertSessionHasNoErrors();
    expect($created->fresh())->monthly_rent->toBe('400.00')->status->toBe(UnitStatus::Maintenance);

    $this->actingAs($owner)->delete(route('units.destroy', $created));
    expect(Unit::find($created->id))->toBeNull();
});

test('occupied unit keeps status and cannot be deleted', function () {
    $tenancy = Tenancy::factory()->create();
    $unit = $tenancy->unit;
    $owner = $unit->property->owner;

    $this->actingAs($owner)->put(route('units.update', $unit), [
        'code' => $unit->code, 'type' => 'room', 'monthly_rent' => 500, 'deposit' => 0, 'status' => 'vacant',
    ]);
    $this->actingAs($owner)->delete(route('units.destroy', $unit));

    expect($unit->fresh())->not->toBeNull()->status->toBe(UnitStatus::Occupied);
});

test('another landlord cannot add units to my property', function () {
    $property = Property::factory()->create();
    $intruder = User::factory()->landlord()->create();

    $this->actingAs($intruder)
        ->post(route('properties.units.store', $property), ['code' => 'X', 'type' => 'room', 'monthly_rent' => 1, 'deposit' => 0])
        ->assertForbidden();
});
