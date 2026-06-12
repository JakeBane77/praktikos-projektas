<?php

use App\Models\Alliance;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('alliance.{alliance}.chat', function (User $user, Alliance $alliance): bool {
    return $user->allianceMembership()
        ->where('alliance_id', $alliance->id)
        ->exists();
});
