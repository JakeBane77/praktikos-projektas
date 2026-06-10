<?php

namespace App\Policies;

use App\Models\Alliance;
use App\Models\AllianceApplication;
use App\Models\AllianceMembership;
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

    public function apply(User $user, Alliance $alliance): bool
    {
        return ! $alliance->is_open && ! $this->hasAlliance($user);
    }

    public function leave(User $user, Alliance $alliance): bool
    {
        $membership = $user->allianceMembership;

        return $membership !== null
            && (int) $membership->alliance_id === (int) $alliance->id;
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

    public function reviewApplication(User $user, Alliance $alliance, AllianceApplication $application): bool
    {
        if ((int) $application->alliance_id !== (int) $alliance->id) {
            return false;
        }

        return $this->updateVisibility($user, $alliance);
    }

    public function kick(User $user, Alliance $alliance, AllianceMembership $targetMembership): bool
    {
        if ((int) $targetMembership->alliance_id !== (int) $alliance->id) {
            return false;
        }

        if ((int) $targetMembership->user_id === (int) $user->id) {
            return false;
        }

        if ((int) $alliance->leader_id === (int) $user->id) {
            return $targetMembership->role !== 'leader';
        }

        $membership = $user->allianceMembership;

        return $membership !== null
            && (int) $membership->alliance_id === (int) $alliance->id
            && $membership->role === 'officer'
            && $targetMembership->role === 'member';
    }

    public function promote(User $user, Alliance $alliance, AllianceMembership $targetMembership): bool
    {
        return (int) $alliance->leader_id === (int) $user->id
            && (int) $targetMembership->alliance_id === (int) $alliance->id
            && $targetMembership->role === 'member';
    }

    public function demote(User $user, Alliance $alliance, AllianceMembership $targetMembership): bool
    {
        return (int) $alliance->leader_id === (int) $user->id
            && (int) $targetMembership->alliance_id === (int) $alliance->id
            && $targetMembership->role === 'officer';
    }

    public function transferLeadership(User $user, Alliance $alliance, AllianceMembership $targetMembership): bool
    {
        return (int) $alliance->leader_id === (int) $user->id
            && (int) $targetMembership->alliance_id === (int) $alliance->id
            && (int) $targetMembership->user_id !== (int) $user->id;
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
