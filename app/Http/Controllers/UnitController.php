<?php

namespace App\Http\Controllers;

use App\Enums\UnitStatus;
use App\Http\Requests\Property\UnitRequest;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function store(UnitRequest $request, Property $property): RedirectResponse
    {
        $property->units()->create([
            ...$request->validated(),
            'status' => $request->validated('status', UnitStatus::Vacant->value),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit added.']);

        return back();
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $data = $request->validated();

        // Occupied units keep their status until the tenancy ends.
        if ($unit->status === UnitStatus::Occupied) {
            unset($data['status']);
        }

        $unit->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit updated.']);

        return back();
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        Gate::authorize('delete', $unit);

        if ($unit->tenancies()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This unit has tenancy records and cannot be deleted.']);

            return back();
        }

        $unit->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit deleted.']);

        return back();
    }
}
