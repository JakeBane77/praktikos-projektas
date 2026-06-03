<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeatherSnapshot;

class WeatherSnapshotPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WeatherSnapshot $snapshot): bool
    {
        return (int) $snapshot->user_id === (int) $user->id;
    }

    public function delete(User $user, WeatherSnapshot $snapshot): bool
    {
        return (int) $snapshot->user_id === (int) $user->id;
    }
}
