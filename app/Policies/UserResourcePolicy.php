<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserResource;

class UserResourcePolicy
{
    public function collect(User $user, UserResource $resources): bool
    {
        return (int) $resources->user_id === (int) $user->id;
    }

    public function prestige(User $user, UserResource $resources): bool
    {
        return (int) $resources->user_id === (int) $user->id;
    }
}
