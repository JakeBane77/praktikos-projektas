<?php

namespace App\Policies;

use App\Models\Alliance;
use App\Models\User;

class AlliancePolicy
{
    public function create(User $user): bool
    {
        return ! $this->hasAlliance($user);
    }

    public function join(User $user, Alliance $alliance): bool
    {
        return $alliance->is_open && ! $this->hasAlliance($user);
    }

    public function leave(User $user, Alliance $alliance): bool
    {
        $membership = $user->allianceMembership;

        return $membership !== null
            && (int) $membership->alliance_id === (int) $alliance->id
            && $membership->role !== 'leader';
    }

    public function update(User $user, Alliance $alliance): bool
    {
        return (int) $alliance->leader_id === (int) $user->id;
    }

    public function updateVisibility(User $user, Alliance $alliance): bool
    {
        if ((int) $alliance->leader_id === (int) $user->id) {
            return true;
        }

        $membership = $user->allianceMembership;

        return $membership !== null
            && (int) $membership->alliance_id === (int) $alliance->id
            && $membership->role === 'officer';
    }

    public function delete(User $user, Alliance $alliance): bool
    {
        return (int) $alliance->leader_id === (int) $user->id;
    }

    private function hasAlliance(User $user): bool
    {
        return $user->allianceMembership()->exists() || $user->ledAlliance()->exists();
    }
}
