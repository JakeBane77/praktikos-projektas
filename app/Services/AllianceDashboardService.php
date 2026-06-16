<?php

namespace App\Services;

use App\Models\Alliance;
use App\Models\AllianceApplication;
use App\Models\AllianceChatMessage;
use App\Models\AllianceCreationLog;
use App\Models\AllianceGoalContribution;
use App\Models\AllianceMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class AllianceDashboardService
{
    private const ALLIANCE_CREATION_COOLDOWN_HOURS = 24;

    private const ALLIANCE_MEMBER_LIMIT = 20;

    private const ALLIANCE_ROLE_ORDER = [
        'leader' => 0,
        'officer' => 1,
        'member' => 2,
    ];

    public function __construct(private readonly AllianceGoalService $allianceGoalService) {}

    /**
     * @return array{current: array<string, mixed>|null, available: array<int, array<string, mixed>>, canCreate: bool, creationCooldownEndsAt: string|null}
     */
    public function cardsFor(User $user, string $search = ''): array
    {
        $currentAlliance = $this->currentAllianceFor($user);
        $hasAlliance = $currentAlliance instanceof Alliance;
        $cooldownEndsAt = $this->allianceCreationCooldownEndsAt($user);

        $availableAlliances = Alliance::query()
            ->with(['applications.user', 'leader', 'memberships.user'])
            ->withCount('memberships')
            ->when($currentAlliance, fn ($query) => $query->whereKeyNot($currentAlliance->id))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('is_open')
            ->orderByDesc('memberships_count')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (Alliance $alliance): array => $this->allianceSummaryCardFor(
                user: $user,
                alliance: $alliance,
                canJoin: ! $hasAlliance
                    && $alliance->is_open
                    && (int) $alliance->memberships_count < self::ALLIANCE_MEMBER_LIMIT,
            ))
            ->values()
            ->all();

        return [
            'current' => $currentAlliance instanceof Alliance
                ? $this->currentAllianceCardFor($user, $currentAlliance)
                : null,
            'available' => $availableAlliances,
            'canCreate' => ! $hasAlliance && $cooldownEndsAt === null,
            'creationCooldownEndsAt' => $cooldownEndsAt?->toIso8601String(),
        ];
    }

    private function currentAllianceFor(User $user): ?Alliance
    {
        $membership = $user->allianceMembership()
            ->with('alliance')
            ->first();

        if ($membership instanceof AllianceMembership && $membership->alliance instanceof Alliance) {
            return $membership->alliance;
        }

        return $user->ledAlliance()->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function currentAllianceCardFor(User $user, Alliance $alliance): array
    {
        $alliance->loadMissing(['applications.user', 'leader', 'memberships.user']);
        $alliance->loadCount('memberships');

        $membership = $alliance->memberships
            ->firstWhere('user_id', $user->id);

        $role = $membership instanceof AllianceMembership
            ? $membership->role
            : ((int) $alliance->leader_id === (int) $user->id ? 'leader' : 'member');

        return [
            ...$this->allianceSummaryCardFor($user, $alliance, false),
            'currentUserRole' => $role,
            'canUpdate' => Gate::forUser($user)->allows('update', $alliance),
            'canUpdateVisibility' => Gate::forUser($user)->allows('updateVisibility', $alliance),
            'canLeave' => Gate::forUser($user)->allows('leave', $alliance),
            'canDisband' => Gate::forUser($user)->allows('delete', $alliance),
            'members' => $this->allianceMemberCardsFor($user, $alliance, true),
            'contributions' => $this->allianceContributionCardsFor($alliance),
            'chatMessages' => Gate::forUser($user)->allows('viewChat', $alliance)
                ? $this->allianceChatMessageCardsFor($user, $alliance)
                : [],
            'applications' => Gate::forUser($user)->allows('updateVisibility', $alliance)
                ? $this->allianceApplicationCardsFor($alliance)
                : [],
            'goal' => $this->allianceGoalService->currentGoalCardFor($alliance),
            'activeGoalBonus' => $this->allianceGoalService->previousBonusCardFor($alliance),
        ];
    }

    /**
     * @return array<int, array{id: int, userId: int, userName: string, message: string, sentAt: string|null, isCurrentUser: bool}>
     */
    private function allianceChatMessageCardsFor(User $user, Alliance $alliance): array
    {
        return AllianceChatMessage::query()
            ->with('user')
            ->where('alliance_id', $alliance->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->map(fn (AllianceChatMessage $message): array => [
                'id' => $message->id,
                'userId' => $message->user_id,
                'userName' => $message->user instanceof User ? $message->user->name : 'Unknown player',
                'message' => $message->message,
                'sentAt' => $message->created_at?->format('Y-m-d H:i'),
                'isCurrentUser' => (int) $message->user_id === (int) $user->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, userId: int, userName: string, goalName: string, resourceType: string, resourceLabel: string, amount: int, amountLabel: string, contributedAt: string|null}>
     */
    private function allianceContributionCardsFor(Alliance $alliance): array
    {
        return AllianceGoalContribution::query()
            ->with(['goal', 'user'])
            ->whereHas('goal', fn ($query) => $query->where('alliance_id', $alliance->id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AllianceGoalContribution $contribution): array => [
                'id' => $contribution->id,
                'userId' => $contribution->user_id,
                'userName' => $contribution->user instanceof User ? $contribution->user->name : 'Unknown player',
                'goalName' => $contribution->goal->name,
                'resourceType' => $contribution->resource_type,
                'resourceLabel' => ucfirst($contribution->resource_type),
                'amount' => (int) $contribution->amount,
                'amountLabel' => number_format((int) $contribution->amount),
                'contributedAt' => $contribution->created_at?->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, userId: int, userName: string, appliedAt: string|null}>
     */
    private function allianceApplicationCardsFor(Alliance $alliance): array
    {
        $alliance->loadMissing('applications.user');

        return $alliance->applications
            ->sortBy('created_at')
            ->map(fn (AllianceApplication $application): array => [
                'id' => $application->id,
                'userId' => $application->user_id,
                'userName' => $application->user->name,
                'appliedAt' => $application->created_at?->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allianceMemberCardsFor(User $user, Alliance $alliance, bool $includeActions): array
    {
        $alliance->loadMissing('memberships.user');

        return $alliance->memberships
            ->sort($this->compareAllianceMemberships(...))
            ->map(fn (AllianceMembership $membership): array => [
                'id' => $membership->id,
                'userId' => $membership->user_id,
                'name' => $membership->user->name,
                'role' => $membership->role,
                'totalContributed' => (int) $membership->total_contributed,
                'joinedAt' => $membership->joined_at?->format('Y-m-d H:i'),
                'isCurrentUser' => (int) $membership->user_id === (int) $user->id,
                'canKick' => $includeActions && Gate::forUser($user)->allows('kick', [$alliance, $membership]),
                'canPromote' => $includeActions && Gate::forUser($user)->allows('promote', [$alliance, $membership]),
                'canDemote' => $includeActions && Gate::forUser($user)->allows('demote', [$alliance, $membership]),
                'canTransferLeadership' => $includeActions && Gate::forUser($user)->allows('transferLeadership', [$alliance, $membership]),
            ])
            ->values()
            ->all();
    }

    private function compareAllianceMemberships(AllianceMembership $first, AllianceMembership $second): int
    {
        $roleComparison = (self::ALLIANCE_ROLE_ORDER[$first->role] ?? 99)
            <=> (self::ALLIANCE_ROLE_ORDER[$second->role] ?? 99);

        if ($roleComparison !== 0) {
            return $roleComparison;
        }

        $contributionComparison = (int) $second->total_contributed <=> (int) $first->total_contributed;

        if ($contributionComparison !== 0) {
            return $contributionComparison;
        }

        return strcasecmp($first->user->name, $second->user->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function allianceSummaryCardFor(User $user, Alliance $alliance, bool $canJoin): array
    {
        $alliance->loadMissing('applications');
        $application = $alliance->applications
            ->firstWhere('user_id', $user->id);

        return [
            'id' => $alliance->id,
            'name' => $alliance->name,
            'slug' => $alliance->slug,
            'description' => $alliance->description,
            'leaderName' => $alliance->leader->name,
            'memberLimit' => self::ALLIANCE_MEMBER_LIMIT,
            'memberCount' => (int) ($alliance->memberships_count ?? $alliance->memberships()->count()),
            'isOpen' => (bool) $alliance->is_open,
            'canJoin' => $canJoin,
            'canApply' => ! $alliance->is_open
                && ! $this->userHasAlliance($user)
                && ! ($application instanceof AllianceApplication),
            'hasPendingApplication' => $application instanceof AllianceApplication,
            'members' => $this->allianceMemberCardsFor($user, $alliance, false),
        ];
    }

    private function allianceCreationCooldownEndsAt(User $user): ?CarbonImmutable
    {
        $lastCreatedAt = AllianceCreationLog::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->value('created_at');

        if (! $lastCreatedAt) {
            return null;
        }

        $cooldownEndsAt = CarbonImmutable::parse($lastCreatedAt)
            ->addHours(self::ALLIANCE_CREATION_COOLDOWN_HOURS);

        return $cooldownEndsAt->isFuture() ? $cooldownEndsAt : null;
    }

    private function userHasAlliance(User $user): bool
    {
        return $user->allianceMembership()->exists() || $user->ledAlliance()->exists();
    }
}
