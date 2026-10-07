<?php

use App\Models\Invoice;
use App\Models\Property;
use App\Models\User;
use App\Support\GuestSandbox;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config(['kuncia.guest.enabled' => true, 'kuncia.guest.email' => 'guest@kuncia.test', 'kuncia.guest.password' => 'guest1234']);
});

test('guest reset builds a sandbox and never touches real landlords', function () {
    $family = User::factory()->landlord()->create();
    $familyProperty = Property::factory()->for($family, 'owner')->create();

    $this->artisan('kuncia:guest-reset')->assertSuccessful();
    $guest = User::where('email', 'guest@kuncia.test')->first();

    expect($guest->is_guest)->toBeTrue()
        ->and($guest->properties)->toHaveCount(2)
        ->and($guest->tenants)->toHaveCount(6)
        ->and(Hash::check('guest1234', $guest->password))->toBeTrue();

    // Visitor messes around, then nightly reset
    $guest->properties()->create(['name' => 'junk', 'type' => 'house', 'address' => 'x', 'city' => 'x', 'state' => 'Melaka', 'postcode' => '75000']);
    $this->artisan('kuncia:guest-reset')->assertSuccessful();

    $fresh = User::where('email', 'guest@kuncia.test')->sole();
    expect($fresh->properties()->count())->toBe(2)
        ->and(Property::where('name', 'junk')->exists())->toBeFalse()
        ->and(Property::find($familyProperty->id))->not->toBeNull()
        ->and(User::find($family->id))->not->toBeNull();
});

test('guest cannot see family data', function () {
    $family = User::factory()->landlord()->create();
    $property = Property::factory()->for($family, 'owner')->create();
    $guest = app(GuestSandbox::class)->reset();

    $this->actingAs($guest)->get(route('properties.show', $property))->assertForbidden();
    expect(Invoice::forLandlord($guest)->count())->toBeGreaterThan(0)
        ->and(Invoice::forLandlord($family)->count())->toBe(0);
});

test('guest cannot change password, email or delete the account', function () {
    $guest = app(GuestSandbox::class)->reset();

    $this->actingAs($guest)->patch(route('profile.update'), ['name' => 'Hacker', 'email' => 'x@x.com']);
    $this->actingAs($guest)->put(route('user-password.update'), [
        'current_password' => 'guest1234', 'password' => 'newpassword1', 'password_confirmation' => 'newpassword1',
    ]);
    $this->actingAs($guest)->delete(route('profile.destroy'), ['password' => 'guest1234']);

    $guest->refresh();
    expect($guest->email)->toBe('guest@kuncia.test')
        ->and(Hash::check('guest1234', $guest->password))->toBeTrue();
});

test('login page shows the guest hint only when enabled', function () {
    $this->get(route('login'))->assertInertia(fn ($p) => $p->where('guestLogin.email', 'guest@kuncia.test'));

    config(['kuncia.guest.enabled' => false]);
    $this->get(route('login'))->assertInertia(fn ($p) => $p->where('guestLogin', null));
});

test('registration can be switched off', function () {
    config(['kuncia.registration' => false]);

    $this->get(route('register'))->assertNotFound();
    $this->post(route('register.store'), [
        'name' => 'Stranger', 'email' => 'stranger@example.com', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});

test('create-landlord command makes a landlord', function () {
    $this->artisan('kuncia:create-landlord')
        ->expectsQuestion('Full name', 'Mak Syaheer')
        ->expectsQuestion('Email', 'mak@example.com')
        ->expectsQuestion('Password (min 8)', 'secret-pass-123')
        ->assertSuccessful();

    expect(User::where('email', 'mak@example.com')->first()->isLandlord())->toBeTrue();
});
