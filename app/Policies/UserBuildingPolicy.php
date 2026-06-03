<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserBuilding;

class UserBuildingPolicy
{
    public function upgrade(User $user, UserBuilding $building): bool
    {
        return (int) $building->user_id === (int) $user->id;
    }
}
