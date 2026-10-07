<?php

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $tenancy = Tenancy::factory()->create();
    $this->tenant = $tenancy->tenant;
    $this->unit = $tenancy->unit;
    $this->landlord = $this->unit->property->owner;
    $this->tech = User::factory()->maintenance()->create(['landlord_id' => $this->landlord->id]);
});

function ticketFor($test, array $attrs = []): MaintenanceRequest
{
    return MaintenanceRequest::factory()->create([
        'unit_id' => $test->unit->id,
        'tenant_id' => $test->tenant->id,
        ...$attrs,
    ]);
}

test('tenant reports a problem with photos stored privately', function () {
    Storage::fake('local');

    $this->actingAs($this->tenant)->post(route('my.maintenance.store'), [
        'title' => 'Aircond not cold',
        'description' => 'Blowing warm air since yesterday',
        'priority' => 'high',
        'photos' => [UploadedFile::fake()->image('ac.jpg'), UploadedFile::fake()->image('remote.jpg')],
    ])->assertRedirect();

    $ticket = MaintenanceRequest::sole();
    expect($ticket->unit_id)->toBe($this->unit->id)
        ->and($ticket->status)->toBe(MaintenanceStatus::Open)
        ->and($ticket->attachments)->toHaveCount(2);
    Storage::disk('local')->assertExists($ticket->attachments->first()->path);
});

test('tenant without an active tenancy cannot report', function () {
    $loner = User::factory()->tenant()->create();

    $this->actingAs($loner)->post(route('my.maintenance.store'), [
        'title' => 'X', 'description' => 'Y', 'priority' => 'low',
    ]);

    expect(MaintenanceRequest::count())->toBe(0);
});

test('more than 3 photos is rejected', function () {
    $this->actingAs($this->tenant)->post(route('my.maintenance.store'), [
        'title' => 'X', 'description' => 'Y', 'priority' => 'low',
        'photos' => array_fill(0, 4, UploadedFile::fake()->image('a.jpg')),
    ])->assertSessionHasErrors('photos');
});

test('landlord assigns own staff only', function () {
    $ticket = ticketFor($this);
    $otherTech = User::factory()->maintenance()->create();

    $this->actingAs($this->landlord)
        ->post(route('maintenance.assign', $ticket), ['assigned_to' => $otherTech->id, 'priority' => 'high'])
        ->assertSessionHasErrors('assigned_to');

    $this->actingAs($this->landlord)
        ->post(route('maintenance.assign', $ticket), ['assigned_to' => $this->tech->id, 'priority' => 'high'])
        ->assertSessionHasNoErrors();

    expect($ticket->fresh())->assigned_to->toBe($this->tech->id)->priority->toBe(MaintenancePriority::High);
});

test('full status flow: staff works it, landlord closes', function () {
    $ticket = ticketFor($this, ['assigned_to' => $this->tech->id]);
    $url = route('maintenance.status', $ticket);

    $this->actingAs($this->tech)->post($url, ['status' => 'in_progress'])->assertSessionHasNoErrors();
    $this->actingAs($this->tech)->post($url, ['status' => 'resolved'])->assertSessionHasErrors('note');
    $this->actingAs($this->tech)->post($url, ['status' => 'resolved', 'note' => 'Topped up gas'])->assertSessionHasNoErrors();

    expect($ticket->fresh())->status->toBe(MaintenanceStatus::Resolved)->resolved_at->not->toBeNull();

    // Staff cannot close, landlord can
    $this->actingAs($this->tech)->post($url, ['status' => 'closed'])->assertSessionHasErrors('status');
    $this->actingAs($this->landlord)->post($url, ['status' => 'closed'])->assertSessionHasNoErrors();

    expect($ticket->fresh()->status)->toBe(MaintenanceStatus::Closed);
});

test('only the landlord can close directly, with a note; tenant cannot change status', function () {
    $ticket = ticketFor($this, ['assigned_to' => $this->tech->id]);
    $url = route('maintenance.status', $ticket);

    $this->actingAs($this->tech)->post($url, ['status' => 'closed', 'note' => 'x'])->assertSessionHasErrors('status');
    $this->actingAs($this->tenant)->post($url, ['status' => 'in_progress'])->assertSessionHasErrors('status');
    $this->actingAs($this->tenant)->post($url, ['status' => 'resolved', 'note' => 'x'])->assertSessionHasErrors('status');
    $this->actingAs($this->landlord)->post($url, ['status' => 'closed'])->assertSessionHasErrors('note');

    expect($ticket->fresh()->status)->toBe(MaintenanceStatus::Open);

    $this->actingAs($this->landlord)->post($url, ['status' => 'closed', 'note' => 'Fixed by our own plumber'])
        ->assertSessionHasNoErrors();

    expect($ticket->fresh())
        ->status->toBe(MaintenanceStatus::Closed)
        ->resolution_note->toBe('Fixed by our own plumber')
        ->resolved_at->not->toBeNull();
});

test('landlord can reopen a resolved ticket', function () {
    $ticket = ticketFor($this, ['status' => MaintenanceStatus::Resolved, 'resolved_at' => now(), 'resolution_note' => 'done']);

    $this->actingAs($this->landlord)->post(route('maintenance.status', $ticket), ['status' => 'in_progress'])
        ->assertSessionHasNoErrors();

    expect($ticket->fresh())->status->toBe(MaintenanceStatus::InProgress)->resolved_at->toBeNull();
});

test('who can see a ticket', function () {
    $ticket = ticketFor($this, ['assigned_to' => $this->tech->id]);
    $show = route('maintenance.show', $ticket);

    $this->actingAs($this->landlord)->get($show)->assertOk();
    $this->actingAs($this->tenant)->get($show)->assertOk();
    $this->actingAs($this->tech)->get($show)->assertOk();
    $this->actingAs(User::factory()->maintenance()->create())->get($show)->assertForbidden();
    $this->actingAs(User::factory()->landlord()->create())->get($show)->assertForbidden();
    $this->actingAs(User::factory()->tenant()->create())->get($show)->assertForbidden();
});

test('photos are only served to people on the ticket', function () {
    Storage::fake('local');
    $ticket = ticketFor($this);
    $path = UploadedFile::fake()->image('leak.jpg')->store('maintenance', 'local');
    $photo = $ticket->attachments()->create(['path' => $path, 'original_name' => 'leak.jpg']);

    $this->actingAs($this->tenant)->get(route('attachments.show', $photo))->assertOk();
    $this->actingAs(User::factory()->tenant()->create())->get(route('attachments.show', $photo))->assertForbidden();
});

test('lists are scoped per role', function () {
    ticketFor($this, ['assigned_to' => $this->tech->id]);
    ticketFor($this);
    MaintenanceRequest::factory()->create(); // another landlord's

    $this->actingAs($this->landlord)->get(route('maintenance.index'))
        ->assertInertia(fn (Assert $p) => $p->component('maintenance/index')->has('tickets', 2)->has('staff', 1));
    $this->actingAs($this->tech)->get(route('tasks.index'))
        ->assertInertia(fn (Assert $p) => $p->component('tasks/index')->has('tickets', 1));
    $this->actingAs($this->tenant)->get(route('my.maintenance'))
        ->assertInertia(fn (Assert $p) => $p->component('my/maintenance')->has('tickets', 2));
});

test('landlord adds maintenance staff', function () {
    $this->actingAs($this->landlord)->post(route('staff.store'), [
        'name' => 'Ah Seng', 'email' => 'seng@example.com', 'phone' => '0123456789',
    ])->assertSessionHasNoErrors();

    $seng = User::where('email', 'seng@example.com')->first();
    expect($seng->isMaintenance())->toBeTrue()->and($seng->landlord_id)->toBe($this->landlord->id);
});
