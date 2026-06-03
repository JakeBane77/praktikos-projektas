<?php

namespace App\Policies;

use App\Models\Minigame;
use App\Models\User;

class MinigamePolicy
{
    public function complete(User $user, Minigame $minigame): bool
    {
        return (int) $minigame->user_id === (int) $user->id;
    }
}
