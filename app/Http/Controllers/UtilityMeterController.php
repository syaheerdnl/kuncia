<?php

namespace App\Http\Controllers;

use App\Http\Requests\Utilities\MeterRequest;
use App\Models\Property;
use App\Models\UtilityMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Meters (TNB / water accounts) on a property and the units that share them. */
class UtilityMeterController extends Controller
{
    public function store(MeterRequest $request, Property $property): RedirectResponse
    {
        DB::transaction(function () use ($request, $property) {
            $meter = $property->meters()->create($request->safe()->except('unit_ids'));
            $meter->units()->sync($request->validated('unit_ids'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meter added.']);

        return back();
    }

    public function update(MeterRequest $request, UtilityMeter $meter): RedirectResponse
    {
        DB::transaction(function () use ($request, $meter) {
            $meter->update($request->safe()->except('unit_ids'));
            $meter->units()->sync($request->validated('unit_ids'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meter saved. New bills use the updated units.']);

        return back();
    }

    public function destroy(UtilityMeter $meter): RedirectResponse
    {
        Gate::authorize('delete', $meter);

        if ($meter->bills()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This meter has bills. Remove them first.']);

            return back();
        }

        $meter->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meter removed.']);

        return back();
    }
}
