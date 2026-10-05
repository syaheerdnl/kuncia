<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function view(User $user, Unit $unit): bool
    {
        return $user->isLandlord() && $unit->property->owner_id === $user->id;
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit);
    }
}
