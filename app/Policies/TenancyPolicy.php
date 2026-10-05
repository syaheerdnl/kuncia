<?php

namespace App\Policies;

use App\Models\Tenancy;
use App\Models\User;

class TenancyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLandlord();
    }

    public function view(User $user, Tenancy $tenancy): bool
    {
        return $this->ownsUnit($user, $tenancy)
            || ($user->isTenant() && $tenancy->tenant_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function update(User $user, Tenancy $tenancy): bool
    {
        return $this->ownsUnit($user, $tenancy);
    }

    private function ownsUnit(User $user, Tenancy $tenancy): bool
    {
        return $user->isLandlord() && $tenancy->unit->property->owner_id === $user->id;
    }
}
