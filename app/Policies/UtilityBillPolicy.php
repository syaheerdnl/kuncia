<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UtilityBill;

class UtilityBillPolicy
{
    /** Landlord, or a tenant who was charged a share of it. */
    public function view(User $user, UtilityBill $bill): bool
    {
        return $this->delete($user, $bill)
            || ($user->isTenant() && $bill->shares()->whereHas('tenancy', fn ($q) => $q->where('tenant_id', $user->id))->exists());
    }

    public function delete(User $user, UtilityBill $bill): bool
    {
        return $user->isLandlord() && $bill->meter->property->owner_id === $user->id;
    }
}
