<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function view(User $user, MaintenanceRequest $request): bool
    {
        return $this->isLandlordOf($user, $request)
            || ($user->isTenant() && $request->tenant_id === $user->id)
            || ($user->isMaintenance() && $request->assigned_to === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isTenant();
    }

    /** Landlord assigns staff / changes priority. */
    public function assign(User $user, MaintenanceRequest $request): bool
    {
        return $this->isLandlordOf($user, $request);
    }

    /** Landlord or the assigned staff move the status forward. */
    public function updateStatus(User $user, MaintenanceRequest $request): bool
    {
        return $this->isLandlordOf($user, $request)
            || ($user->isMaintenance() && $request->assigned_to === $user->id);
    }

    private function isLandlordOf(User $user, MaintenanceRequest $request): bool
    {
        return $user->isLandlord() && $request->unit->property->owner_id === $user->id;
    }
}
