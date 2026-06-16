<?php

namespace App\Services;

use App\Models\Minigame;
use App\Models\ResourceCollection;
use App\Models\User;
use App\Support\MinigameStamina;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class MinigameDashboardService
{
    private const RESOURCE_TYPES = ['gold', 'wood', 'stone', 'food'];

    private const RESOURCE_DISPLAY_ORDER = ['wood', 'food', 'stone', 'gold'];

    /**
     * @return Collection<int, Minigame>
     */
    public function minigamesFor(User $user): Collection
    {
        foreach (self::RESOURCE_TYPES as $resource) {
            $this->minigameFor($user, $resource);
        }

        return Minigame::query()
            ->where('user_id', $user->id)
            ->get()
            ->sortBy(fn (Minigame $minigame): int => (int) array_search($minigame->resource, self::RESOURCE_DISPLAY_ORDER, true))
            ->values();
    }

    public function minigameFor(User $user, string $resource): Minigame
    {
        return Minigame::firstOrCreate(
            [
                'user_id' => $user->id,
                'resource' => $resource,
            ],
            [
                'completions' => 0,
                'resources_gained' => 0,
            ],
        );
    }

    /**
     * @param  Collection<int, Minigame>  $minigames
     * @param  array{gold: int, wood: int, stone: int, food: int}  $productionRates
     * @return array<int, array{resource: string, label: string, currentProduction: int, reward: int, rewardLabel: string, completions: int, resourcesGained: int, stamina: array{current: int, max: int, used: int, isAvailable: bool, availableInSeconds: int, label: string}}>
     */
    public function cardsFor(User $user, Collection $minigames, array $productionRates): array
    {
        $minigamesByResource = $minigames->keyBy('resource');
        $stamina = $this->staminaCardFor($user);

        return collect(self::RESOURCE_DISPLAY_ORDER)
            ->map(function (string $resource) use ($minigamesByResource, $productionRates, $stamina): array {
                $currentProduction = (int) $productionRates[$resource];
                $reward = $this->rewardFor($currentProduction);
                $minigame = $minigamesByResource->get($resource);

                return [
                    'resource' => $resource,
                    'label' => str($resource)->title()->append(' minigame')->toString(),
                    'currentProduction' => $currentProduction,
                    'reward' => $reward,
                    'rewardLabel' => '+'.number_format($reward).' '.$resource,
                    'completions' => $minigame instanceof Minigame ? (int) $minigame->completions : 0,
                    'resourcesGained' => $minigame instanceof Minigame ? (int) $minigame->resources_gained : 0,
                    'stamina' => $stamina,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{current: int, max: int, used: int, isAvailable: bool, availableInSeconds: int, label: string}
     */
    public function staminaCardFor(User $user): array
    {
        $windowStartsAt = now()->subSeconds(MinigameStamina::WINDOW_SECONDS);
        $used = ResourceCollection::query()
            ->where('user_id', $user->id)
            ->where('source', 'like', MinigameStamina::sourcePattern())
            ->where('collected_at', '>', $windowStartsAt)
            ->count();
        $current = max(0, MinigameStamina::MAX_COMPLETIONS_PER_HOUR - $used);
        $availableInSeconds = 0;

        if ($used > 0) {
            $oldestCollectionTime = ResourceCollection::query()
                ->where('user_id', $user->id)
                ->where('source', 'like', MinigameStamina::sourcePattern())
                ->where('collected_at', '>', $windowStartsAt)
                ->orderBy('collected_at')
                ->value('collected_at');

            if ($oldestCollectionTime !== null) {
                $availableInSeconds = max(
                    1,
                    (int) now()->diffInSeconds(
                        CarbonImmutable::parse($oldestCollectionTime)->addSeconds(MinigameStamina::WINDOW_SECONDS),
                        false,
                    ),
                );
            }
        }

        return [
            'current' => $current,
            'max' => MinigameStamina::MAX_COMPLETIONS_PER_HOUR,
            'used' => $used,
            'isAvailable' => $current > 0,
            'availableInSeconds' => $availableInSeconds,
            'label' => number_format($current).' / '.number_format(MinigameStamina::MAX_COMPLETIONS_PER_HOUR),
        ];
    }

    public function rewardFor(int $currentProduction): int
    {
        return (int) ceil(1 + ($currentProduction * 0.02));
    }
}
