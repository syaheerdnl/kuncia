<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UtilityMeter;

class UtilityMeterPolicy
{
    /** Only the landlord who owns the property manages its meters and bills. */
    public function update(User $user, UtilityMeter $meter): bool
    {
        return $user->isLandlord() && $meter->property->owner_id === $user->id;
    }

    public function delete(User $user, UtilityMeter $meter): bool
    {
        return $this->update($user, $meter);
    }
}
