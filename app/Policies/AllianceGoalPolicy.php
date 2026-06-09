<?php

namespace App\Policies;

use App\Models\AllianceGoal;
use App\Models\User;

class AllianceGoalPolicy
{
    public function contribute(User $user, AllianceGoal $goal): bool
    {
        $membership = $user->allianceMembership;

        return $membership !== null
            && (int) $membership->alliance_id === (int) $goal->alliance_id
            && $goal->status === 'active';
    }
}
