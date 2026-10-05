<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Enums\TenancyStatus;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Http\Requests\Property\PropertyRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\MalaysianStates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Property::class);

        /** @var User $user */
        $user = $request->user();

        $properties = $user->properties()
            ->withCount([
                'units',
                'units as occupied_count' => fn ($q) => $q->where('status', UnitStatus::Occupied),
            ])
            ->latest()
            ->get()
            ->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type->label(),
                'city' => $p->city,
                'state' => $p->state,
                'cover_url' => $p->cover_image ? Storage::url($p->cover_image) : null,
                'units_count' => $p->units_count,
                'occupied_count' => $p->occupied_count,
            ]);

        return Inertia::render('properties/index', ['properties' => $properties]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Property::class);

        return Inertia::render('properties/create', $this->formOptions());
    }

    public function store(PropertyRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->safe()->except('cover_image');
        if ($path = $this->storeCover($request)) {
            $data['cover_image'] = $path;
        }

        $property = $user->properties()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property created. Now add some units.']);

        return to_route('properties.show', $property);
    }

    public function show(Property $property): Response
    {
        Gate::authorize('view', $property);

        $property->load(['units' => fn ($q) => $q->orderBy('code'), 'units.activeTenancy.tenant']);

        return Inertia::render('properties/show', [
            'property' => [
                ...$this->propertyData($property),
                'type_label' => $property->type->label(),
                'description' => $property->description,
            ],
            'units' => $property->units->map(fn (Unit $u) => [
                'id' => $u->id,
                'code' => $u->code,
                'type' => $u->type->value,
                'type_label' => $u->type->label(),
                'monthly_rent' => $u->monthly_rent,
                'deposit' => $u->deposit,
                'status' => $u->status->value,
                'tenant' => $u->activeTenancy?->tenant->name,
            ]),
            'unitTypes' => UnitType::options(),
        ]);
    }

    public function edit(Property $property): Response
    {
        Gate::authorize('update', $property);

        return Inertia::render('properties/edit', [
            ...$this->formOptions(),
            'property' => $this->propertyData($property),
        ]);
    }

    public function update(PropertyRequest $request, Property $property): RedirectResponse
    {
        $data = $request->safe()->except('cover_image');
        if ($path = $this->storeCover($request)) {
            if ($property->cover_image) {
                Storage::disk('public')->delete($property->cover_image);
            }
            $data['cover_image'] = $path;
        }

        $property->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property updated.']);

        return to_route('properties.show', $property);
    }

    public function destroy(Property $property): RedirectResponse
    {
        Gate::authorize('delete', $property);

        if ($property->tenancies()->where('tenancies.status', TenancyStatus::Active)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'End all active tenancies before deleting this property.']);

            return back();
        }

        if ($property->tenancies()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This property has tenancy history and cannot be deleted.']);

            return back();
        }

        if ($property->cover_image) {
            Storage::disk('public')->delete($property->cover_image);
        }
        $property->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property deleted.']);

        return to_route('properties.index');
    }

    private function storeCover(PropertyRequest $request): ?string
    {
        $file = $request->file('cover_image');

        return $file instanceof UploadedFile ? ($file->store('properties', 'public') ?: null) : null;
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'types' => PropertyType::options(),
            'states' => MalaysianStates::ALL,
        ];
    }

    /** @return array<string, mixed> */
    private function propertyData(Property $property): array
    {
        return [
            'id' => $property->id,
            'name' => $property->name,
            'type' => $property->type->value,
            'address' => $property->address,
            'city' => $property->city,
            'state' => $property->state,
            'postcode' => $property->postcode,
            'description' => $property->description,
            'cover_url' => $property->cover_image ? Storage::url($property->cover_image) : null,
        ];
    }
}
