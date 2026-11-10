<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The tenant's own home: the unit they rent now, the terms, and who to contact. */
class MyUnitController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $tenant */
        $tenant = $request->user();

        $tenancy = $tenant->activeTenancy()
            ->with('unit.property.owner')
            ->first();

        if (! $tenancy) {
            return Inertia::render('my/unit', ['tenancy' => null]);
        }

        $unit = $tenancy->unit;
        $property = $unit->property;
        $landlord = $property->owner;

        return Inertia::render('my/unit', [
            'tenancy' => [
                'id' => $tenancy->id,
                'status' => $tenancy->status->value,
                'start_date' => $tenancy->start_date->toDateString(),
                'end_date' => $tenancy->end_date?->toDateString(),
                'monthly_rent' => $tenancy->monthly_rent,
                'due_day' => $tenancy->due_day,
                'deposit_amount' => $tenancy->deposit_amount,
                'deposit_status' => $tenancy->deposit_status->value,
                'unit' => [
                    'code' => $unit->code,
                    'type' => $unit->type->label(),
                ],
                'property' => [
                    'name' => $property->name,
                    'type' => $property->type->label(),
                    'address' => implode(', ', array_filter([
                        $property->address,
                        trim($property->postcode.' '.$property->city),
                        $property->state,
                    ])),
                ],
                'landlord' => [
                    'name' => $landlord->name,
                    'email' => $landlord->email,
                    'phone' => $landlord->phone,
                ],
            ],
        ]);
    }
}
