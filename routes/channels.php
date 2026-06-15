<?php

use App\Models\Alliance;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('alliance.{alliance}.chat', function (User $user, Alliance $alliance): bool {
    return $user->allianceMembership()
        ->where('alliance_id', $alliance->id)
        ->exists();
});

Broadcast::channel(
    'alliance.{alliance}.presence',
    function (User $user, Alliance $alliance): array|bool {
        $isMember = $user->allianceMembership()
            ->where('alliance_id', $alliance->id)
            ->exists();

        if (! $isMember) {
            return false;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    },
    ['guards' => ['web']],
);
