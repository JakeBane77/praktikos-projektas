<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAchievement;

class UserAchievementPolicy
{
    public function markUnlockSeen(User $user, UserAchievement $achievement): bool
    {
        return (int) $achievement->user_id === (int) $user->id;
    }
}
