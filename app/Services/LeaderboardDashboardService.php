<?php

namespace App\Services;

use App\Models\Minigame;
use App\Models\User;
use App\Models\UserResource;
use Illuminate\Database\Eloquent\Collection;

class LeaderboardDashboardService
{
    private const RESOURCE_DISPLAY_ORDER = ['wood', 'food', 'stone', 'gold'];

    /**
     * @param  Collection<int, Minigame>  $minigames
     * @return array{defaultKey: string, boards: array<int, array<string, mixed>>}
     */
    public function cardsFor(User $user, UserResource $resources, Collection $minigames): array
    {
        $boards = [
            $this->userResourceLeaderboardFor(
                user: $user,
                key: 'prestige',
                label: 'Prestige',
                metricLabel: 'Prestiges',
                column: 'prestiges',
                currentValue: (int) $resources->prestiges,
            ),
            $this->userResourceLeaderboardFor(
                user: $user,
                key: 'manual_collects',
                label: 'Manual collections',
                metricLabel: 'Collects',
                column: 'manual_collects',
                currentValue: (int) $resources->manual_collects,
            ),
        ];

        foreach (self::RESOURCE_DISPLAY_ORDER as $resource) {
            $minigame = $minigames->firstWhere('resource', $resource);
            $currentValue = $minigame instanceof Minigame ? (int) $minigame->completions : 0;
            $boards[] = $this->minigameLeaderboardFor($user, $resource, $currentValue);
        }

        return [
            'defaultKey' => 'prestige',
            'boards' => $boards,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userResourceLeaderboardFor(
        User $user,
        string $key,
        string $label,
        string $metricLabel,
        string $column,
        int $currentValue,
    ): array {
        $rows = UserResource::query()
            ->with('user')
            ->orderByDesc($column)
            ->orderBy('user_id')
            ->limit(50)
            ->get();

        return [
            'key' => $key,
            'label' => $label,
            'metricLabel' => $metricLabel,
            'currentRank' => $this->userResourceLeaderboardRankFor($column, $currentValue),
            'currentValue' => $currentValue,
            'currentValueLabel' => number_format($currentValue),
            'entries' => $this->leaderboardEntriesFor($rows, $column, $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function minigameLeaderboardFor(User $user, string $resource, int $currentValue): array
    {
        $rows = Minigame::query()
            ->with('user')
            ->where('resource', $resource)
            ->orderByDesc('completions')
            ->orderBy('user_id')
            ->limit(50)
            ->get();

        return [
            'key' => $resource.'_minigame',
            'label' => str($resource)->title()->append(' minigame')->toString(),
            'metricLabel' => 'Completions',
            'currentRank' => $this->minigameLeaderboardRankFor($resource, $currentValue),
            'currentValue' => $currentValue,
            'currentValueLabel' => number_format($currentValue),
            'entries' => $this->leaderboardEntriesFor($rows, 'completions', $user),
        ];
    }

    private function userResourceLeaderboardRankFor(string $column, int $currentValue): int
    {
        return UserResource::query()
            ->where($column, '>', $currentValue)
            ->count() + 1;
    }

    private function minigameLeaderboardRankFor(string $resource, int $currentValue): int
    {
        return Minigame::query()
            ->where('resource', $resource)
            ->where('completions', '>', $currentValue)
            ->count() + 1;
    }

    /**
     * @param  iterable<UserResource|Minigame>  $rows
     * @return array<int, array{rank: int, userId: int, userName: string, value: int, valueLabel: string, isCurrentUser: bool}>
     */
    private function leaderboardEntriesFor(iterable $rows, string $valueColumn, User $currentUser): array
    {
        $rank = 0;
        $position = 0;
        $previousValue = null;

        return collect($rows)
            ->map(function (UserResource|Minigame $row) use ($currentUser, &$position, &$previousValue, &$rank, $valueColumn): array {
                $position += 1;
                $value = (int) $row->{$valueColumn};

                if ($previousValue !== $value) {
                    $rank = $position;
                    $previousValue = $value;
                }

                return [
                    'rank' => $rank,
                    'userId' => (int) $row->user_id,
                    'userName' => $row->user instanceof User ? $row->user->name : 'Unknown player',
                    'value' => $value,
                    'valueLabel' => number_format($value),
                    'isCurrentUser' => (int) $row->user_id === (int) $currentUser->id,
                ];
            })
            ->values()
            ->all();
    }
}
