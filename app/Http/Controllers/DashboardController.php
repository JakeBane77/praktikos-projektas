<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\BuildingType;
use App\Models\ResourceCollection;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBuilding;
use App\Models\UserResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const MAX_ROAD_BUILD_AMOUNT = 1_000_000;

    private const DAILY_BASE_REWARDS = [
        'gold' => 0,
        'wood' => 10,
        'stone' => 0,
        'food' => 10,
    ];

    private const DAILY_PRODUCTION_HOURS = 6;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $resources = $this->resourcesFor($user);
        $buildings = $this->buildingsFor($user);
        $productionBonuses = $this->productionBonusesFor($user);
        $this->applyPassiveProduction($resources, $buildings, $productionBonuses);
        $achievements = $this->syncAchievementsFor($user, $resources, $buildings);
        $productionBonuses = $this->productionBonusesFor($user);

        $productionRates = $this->productionRatesFor($buildings, $productionBonuses);
        $roadLength = $this->roadLengthFor($buildings);

        return Inertia::render('Dashboard', [
            'resources' => [
                'gold' => $resources->gold,
                'wood' => $resources->wood,
                'stone' => $resources->stone,
                'food' => $resources->food,
            ],
            'lifetimeResources' => [
                'gold' => $resources->lifetime_gold,
                'wood' => $resources->lifetime_wood,
                'stone' => $resources->lifetime_stone,
                'food' => $resources->lifetime_food,
            ],
            'resourceRates' => [
                'gold' => $productionRates['gold'],
                'wood' => $productionRates['wood'],
                'stone' => $productionRates['stone'],
                'food' => $productionRates['food'],
            ],
            'lastCollectedAt' => $resources->last_collected_at?->format('H:i') ?? 'Never',
            'canCollect' => $this->canCollect($resources),
            'roadStats' => [
                'length' => $roadLength,
                'rank' => $this->roadLeaderboardRankFor($roadLength),
            ],
            'achievementBonuses' => $this->achievementBonusCardsFor($productionBonuses),
            'achievementUnlocks' => $this->achievementUnlockCardsFor($achievements),
            'buildings' => $buildings->map(fn (UserBuilding $building): array => [
                'id' => $building->id,
                'name' => $building->buildingType->name,
                'level' => $building->level,
                'levelLabel' => $this->buildingLevelLabel($building),
                'description' => $this->buildingDescription($building),
                'production' => $this->buildingProductionLabel($building, $productionBonuses),
                'upgradeCost' => $this->upgradeCostLabel($building),
                'isRoad' => $this->isRoad($building),
                'isMaxLevel' => $this->isMaxLevel($building),
                'canUpgrade' => ! $this->isMaxLevel($building)
                    && $this->canAfford($resources, $this->upgradeCostsFor($building)),
            ])->values(),
            'achievements' => $this->achievementCardsFor($achievements),
        ]);
    }

    public function collect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $resources = $this->resourcesFor($user);
        $buildings = $this->buildingsFor($user);
        $productionBonuses = $this->productionBonusesFor($user);
        $this->applyPassiveProduction($resources, $buildings, $productionBonuses);

        if (! $this->canCollect($resources)) {
            return redirect()
                ->route('dashboard')
                ->withErrors([
                    'collect' => 'You have already collected resources today.',
                ]);
        }

        $collectAmounts = $this->collectAmountsFor($buildings, $productionBonuses);

        $resources->gold += $collectAmounts['gold'];
        $resources->wood += $collectAmounts['wood'];
        $resources->stone += $collectAmounts['stone'];
        $resources->food += $collectAmounts['food'];
        $resources->lifetime_gold += $collectAmounts['gold'];
        $resources->lifetime_wood += $collectAmounts['wood'];
        $resources->lifetime_stone += $collectAmounts['stone'];
        $resources->lifetime_food += $collectAmounts['food'];
        $resources->manual_collects += 1;
        $resources->last_collected_at = now();
        $resources->save();
        $this->syncAchievementsFor($user, $resources, $buildings);

        ResourceCollection::create([
            'user_id' => $user->id,
            'gold' => $collectAmounts['gold'],
            'wood' => $collectAmounts['wood'],
            'stone' => $collectAmounts['stone'],
            'food' => $collectAmounts['food'],
            'source' => 'manual',
            'collected_at' => now(),
        ]);

        return redirect()->route('dashboard');
    }

    public function upgrade(Request $request, UserBuilding $building): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_ROAD_BUILD_AMOUNT],
        ]);

        if ($building->user_id !== $request->user()->id) {
            abort(404);
        }

        $resources = $this->resourcesFor($request->user());
        $buildings = $this->buildingsFor($request->user());
        $this->applyPassiveProduction($resources, $buildings, $this->productionBonusesFor($request->user()));
        $building->load('buildingType');
        $amount = $this->isRoad($building) ? ($validated['amount'] ?? 1) : 1;

        if ($building->buildingType->max_level !== null && ($building->level + $amount) > $building->buildingType->max_level) {
            return redirect()
                ->route('dashboard')
                ->withErrors([
                    'upgrade' => $building->buildingType->name.' is already at max level.',
                ]);
        }

        $costs = $this->upgradeCostsFor($building, $amount);

        if (! $this->canAfford($resources, $costs)) {
            return redirect()
                ->route('dashboard')
                ->withErrors([
                    'upgrade' => 'Not enough resources to upgrade '.$building->buildingType->name.'.',
                ]);
        }

        DB::transaction(function () use ($amount, $building, $costs, $resources): void {
            foreach ($costs as $resource => $cost) {
                $resources->{$resource} -= $cost;
            }

            $resources->save();

            if ($building->level === 0) {
                $building->built_at = now();
            }

            $building->level += $amount;
            $building->save();
        });

        $this->syncAchievementsFor($request->user(), $resources, $this->buildingsFor($request->user()));

        return redirect()->route('dashboard');
    }

    public function markAchievementUnlocksSeen(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        UserAchievement::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $validated['ids'])
            ->whereNotNull('unlocked_at')
            ->whereNull('notification_seen_at')
            ->update([
                'notification_seen_at' => now(),
            ]);

        return redirect()->route('dashboard');
    }

    private function resourcesFor(User $user): UserResource
    {
        return UserResource::firstOrCreate(
            ['user_id' => $user->id],
            [
                'gold' => 0,
                'wood' => 0,
                'stone' => 0,
                'food' => 0,
                'last_produced_at' => now(),
            ],
        );
    }

    private function canCollect(UserResource $resources): bool
    {
        return $resources->last_collected_at === null
            || $resources->last_collected_at->copy()->startOfDay()->lt(today());
    }

    /**
     * @return Collection<int, UserBuilding>
     */
    private function buildingsFor(User $user): Collection
    {
        BuildingType::query()
            ->orderBy('id')
            ->get()
            ->each(function (BuildingType $buildingType) use ($user): void {
                UserBuilding::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'building_type_id' => $buildingType->id,
                    ],
                    [
                        'level' => 0,
                        'built_at' => null,
                    ],
                );
            });

        return UserBuilding::query()
            ->with('buildingType')
            ->where('user_id', $user->id)
            ->get()
            ->sortBy(fn (UserBuilding $building): int => $building->buildingType->id)
            ->values();
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     * @return array{gold: int, wood: int, stone: int, food: int}
     */
    private function productionRatesFor(iterable $buildings, array $productionBonuses = []): array
    {
        $rates = [
            'gold' => 0,
            'wood' => 0,
            'stone' => 0,
            'food' => 0,
        ];

        foreach ($buildings as $building) {
            $resource = $building->buildingType->produces_resource;

            if (! $resource || ! array_key_exists($resource, $rates)) {
                continue;
            }

            $rates[$resource] += $this->productionFor($building, $productionBonuses);
        }

        return $rates;
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     */
    private function roadLengthFor(iterable $buildings): int
    {
        foreach ($buildings as $building) {
            if ($this->isRoad($building)) {
                return $building->level;
            }
        }

        return 0;
    }

    private function roadLeaderboardRankFor(int $roadLength): ?int
    {
        $roadTypeIds = BuildingType::query()
            ->where('slug', 'road')
            ->orWhere('name', 'Road')
            ->orWhere('effect_type', 'road_length')
            ->orWhere('produces_resource', 'roadLength')
            ->pluck('id');

        if ($roadTypeIds->isEmpty()) {
            return null;
        }

        return UserBuilding::query()
            ->whereIn('building_type_id', $roadTypeIds)
            ->where('level', '>', $roadLength)
            ->distinct()
            ->count('user_id') + 1;
    }

    /**
     * @return array{all: int, buildingTypes: array<int, int>}
     */
    private function productionBonusesFor(User $user): array
    {
        $bonuses = [
            'all' => 0,
            'buildingTypes' => [],
        ];

        UserAchievement::query()
            ->with('achievement')
            ->where('user_id', $user->id)
            ->whereNotNull('unlocked_at')
            ->get()
            ->each(function (UserAchievement $userAchievement) use (&$bonuses): void {
                $achievement = $userAchievement->achievement;
                $bonusPercent = (int) $achievement->production_bonus_percent;

                if ($bonusPercent <= 0) {
                    return;
                }

                if ($achievement->bonus_building_type_id === null) {
                    $bonuses['all'] += $bonusPercent;

                    return;
                }

                $bonuses['buildingTypes'][$achievement->bonus_building_type_id] =
                    ($bonuses['buildingTypes'][$achievement->bonus_building_type_id] ?? 0) + $bonusPercent;
            });

        return $bonuses;
    }

    /**
     * @param  array{all?: int, buildingTypes?: array<int, int>}  $productionBonuses
     */
    private function productionBonusPercentFor(BuildingType $buildingType, array $productionBonuses): int
    {
        return (int) ($productionBonuses['all'] ?? 0)
            + (int) ($productionBonuses['buildingTypes'][$buildingType->id] ?? 0);
    }

    /**
     * @param  Collection<int, UserBuilding>  $buildings
     * @return Collection<int, UserAchievement>
     */
    private function syncAchievementsFor(User $user, UserResource $resources, Collection $buildings): Collection
    {
        Achievement::query()
            ->with(['buildingType', 'bonusBuildingType'])
            ->orderBy('id')
            ->get()
            ->each(function (Achievement $achievement) use ($buildings, $resources, $user): void {
                $userAchievement = UserAchievement::firstOrNew([
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                ]);

                $userAchievement->progress = $this->achievementProgressFor($achievement, $resources, $buildings);

                if ($userAchievement->unlocked_at === null && $userAchievement->progress >= $achievement->target_value) {
                    $userAchievement->unlocked_at = now();
                }

                $userAchievement->save();
            });

        return UserAchievement::query()
            ->with(['achievement.buildingType', 'achievement.bonusBuildingType'])
            ->where('user_id', $user->id)
            ->orderBy('achievement_id')
            ->get();
    }

    /**
     * @param  Collection<int, UserBuilding>  $buildings
     */
    private function achievementProgressFor(Achievement $achievement, UserResource $resources, Collection $buildings): int
    {
        return match ($achievement->type) {
            'resource', 'resource_lifetime' => $this->resourceProgressFor($resources, $achievement->resource_type, true),
            'resource_current' => $this->resourceProgressFor($resources, $achievement->resource_type, false),
            'manual_collects' => $resources->manual_collects,
            'building_level' => $this->buildingLevelProgressFor($achievement, $buildings),
            'building_levels', 'total_building_levels', 'buildings_built' => $buildings->sum('level'),
            'road_length' => $this->roadLengthFor($buildings),
            default => 0,
        };
    }

    private function resourceProgressFor(UserResource $resources, ?string $resourceType, bool $lifetime): int
    {
        if (! in_array($resourceType, ['gold', 'wood', 'stone', 'food'], true)) {
            return 0;
        }

        $column = $lifetime ? 'lifetime_'.$resourceType : $resourceType;

        return (int) $resources->{$column};
    }

    /**
     * @param  Collection<int, UserBuilding>  $buildings
     */
    private function buildingLevelProgressFor(Achievement $achievement, Collection $buildings): int
    {
        if ($achievement->building_type_id === null) {
            return $buildings->sum('level');
        }

        return (int) ($buildings->firstWhere('building_type_id', $achievement->building_type_id)?->level ?? 0);
    }

    /**
     * @param  Collection<int, UserAchievement>  $userAchievements
     * @return array<int, array<string, mixed>>
     */
    private function achievementCardsFor(Collection $userAchievements): array
    {
        return $userAchievements
            ->map(function (UserAchievement $userAchievement): array {
                $achievement = $userAchievement->achievement;
                $target = max(1, (int) $achievement->target_value);
                $progress = (int) $userAchievement->progress;

                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'progress' => $progress,
                    'target' => (int) $achievement->target_value,
                    'progressLabel' => number_format(min($progress, $target)).' / '.number_format($achievement->target_value),
                    'progressPercent' => min(100, (int) floor(($progress / $target) * 100)),
                    'isUnlocked' => $userAchievement->unlocked_at !== null,
                    'unlockedAt' => $userAchievement->unlocked_at?->format('Y-m-d H:i'),
                    'rewardLabel' => $this->achievementRewardLabel($achievement),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, UserAchievement>  $userAchievements
     * @return array<int, array<string, mixed>>
     */
    private function achievementUnlockCardsFor(Collection $userAchievements): array
    {
        return $userAchievements
            ->filter(fn (UserAchievement $userAchievement): bool => $userAchievement->unlocked_at !== null
                && $userAchievement->notification_seen_at === null)
            ->map(function (UserAchievement $userAchievement): array {
                $achievement = $userAchievement->achievement;

                return [
                    'id' => $userAchievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'unlockedAt' => $userAchievement->unlocked_at?->format('Y-m-d H:i'),
                    'rewardLabel' => $this->achievementRewardLabel($achievement),
                ];
            })
            ->values()
            ->all();
    }

    private function achievementRewardLabel(Achievement $achievement): string
    {
        $bonusPercent = (int) $achievement->production_bonus_percent;

        if ($bonusPercent <= 0) {
            return 'No production bonus';
        }

        $target = $achievement->bonusBuildingType?->name ?? 'all buildings';

        return '+'.$bonusPercent.'% '.$target.' base production';
    }

    /**
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     * @return array<int, array{id: string, label: string, bonusPercent: int, bonusLabel: string}>
     */
    private function achievementBonusCardsFor(array $productionBonuses): array
    {
        $cards = [];
        $allBonus = (int) ($productionBonuses['all'] ?? 0);

        if ($allBonus > 0) {
            $cards[] = [
                'id' => 'all',
                'label' => 'All buildings',
                'bonusPercent' => $allBonus,
                'bonusLabel' => '+'.number_format($allBonus).'%',
            ];
        }

        $buildingTypeBonuses = $productionBonuses['buildingTypes'] ?? [];
        $buildingTypeNames = BuildingType::query()
            ->whereIn('id', array_keys($buildingTypeBonuses))
            ->pluck('name', 'id');

        foreach ($buildingTypeBonuses as $buildingTypeId => $bonusPercent) {
            $bonusPercent = (int) $bonusPercent;

            if ($bonusPercent <= 0) {
                continue;
            }

            $cards[] = [
                'id' => 'building-'.$buildingTypeId,
                'label' => $buildingTypeNames[$buildingTypeId] ?? 'Unknown building',
                'bonusPercent' => $bonusPercent,
                'bonusLabel' => '+'.number_format($bonusPercent).'%',
            ];
        }

        return $cards;
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     * @return array{gold: int, wood: int, stone: int, food: int}
     */
    private function collectAmountsFor(iterable $buildings, array $productionBonuses = []): array
    {
        $rates = $this->productionRatesFor($buildings, $productionBonuses);

        return [
            'gold' => self::DAILY_BASE_REWARDS['gold'] + ($rates['gold'] * self::DAILY_PRODUCTION_HOURS),
            'wood' => self::DAILY_BASE_REWARDS['wood'] + ($rates['wood'] * self::DAILY_PRODUCTION_HOURS),
            'stone' => self::DAILY_BASE_REWARDS['stone'] + ($rates['stone'] * self::DAILY_PRODUCTION_HOURS),
            'food' => self::DAILY_BASE_REWARDS['food'] + ($rates['food'] * self::DAILY_PRODUCTION_HOURS),
        ];
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     */
    private function applyPassiveProduction(UserResource $resources, iterable $buildings, array $productionBonuses = []): void
    {
        if ($resources->last_produced_at === null) {
            $resources->last_produced_at = now();
            $resources->save();

            return;
        }

        $elapsedHours = (int) $resources->last_produced_at->diffInHours(now());

        if ($elapsedHours <= 0) {
            return;
        }

        $rates = $this->productionRatesFor($buildings, $productionBonuses);
        $amounts = [
            'gold' => $rates['gold'] * $elapsedHours,
            'wood' => $rates['wood'] * $elapsedHours,
            'stone' => $rates['stone'] * $elapsedHours,
            'food' => $rates['food'] * $elapsedHours,
        ];

        $resources->gold += $amounts['gold'];
        $resources->wood += $amounts['wood'];
        $resources->stone += $amounts['stone'];
        $resources->food += $amounts['food'];
        $resources->lifetime_gold += $amounts['gold'];
        $resources->lifetime_wood += $amounts['wood'];
        $resources->lifetime_stone += $amounts['stone'];
        $resources->lifetime_food += $amounts['food'];
        $resources->last_produced_at = $resources->last_produced_at->copy()->addHours($elapsedHours);
        $resources->save();

        if (array_sum($amounts) > 0) {
            ResourceCollection::create([
                'user_id' => $resources->user_id,
                'gold' => $amounts['gold'],
                'wood' => $amounts['wood'],
                'stone' => $amounts['stone'],
                'food' => $amounts['food'],
                'source' => 'passive',
                'collected_at' => now(),
            ]);
        }
    }

    /**
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     */
    private function productionFor(UserBuilding $building, array $productionBonuses = []): int
    {
        if ($building->level === 0) {
            return 0;
        }

        $type = $building->buildingType;
        $multiplier = (float) ($type->production_multiplier ?? 1);
        $bonusPercent = $this->productionBonusPercentFor($type, $productionBonuses);
        $baseProduction = $this->baseProductionFor($type) * (1 + ($bonusPercent / 100));

        return (int) ceil($baseProduction * ($multiplier ** ($building->level - 1)));
    }

    /**
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     */
    private function buildingProductionLabel(UserBuilding $building, array $productionBonuses = []): string
    {
        if ($this->isRoad($building)) {
            return number_format($building->level).' km built';
        }

        if ($building->level === 0) {
            return 'Not producing';
        }

        $resource = $building->buildingType->produces_resource;

        if (! $resource) {
            return str($building->buildingType->effect_type ?? 'Special effect')
                ->replace('_', ' ')
                ->title()
                ->toString();
        }

        return '+'.number_format($this->productionFor($building, $productionBonuses)).' '.$resource.'/hour';
    }

    private function buildingDescription(UserBuilding $building): string
    {
        $type = $building->buildingType;

        if ($this->isRoad($building)) {
            return 'Roads improve settlement reach and are measured in kilometers.';
        }

        if ($type->produces_resource) {
            return 'Produces '.$type->produces_resource.' for your settlement.';
        }

        return 'Provides a settlement bonus instead of direct resource production.';
    }

    private function upgradeCostLabel(UserBuilding $building): string
    {
        $costs = collect($this->upgradeCostsFor($building))
            ->map(fn (int $amount, string $resource): string => number_format($amount).' '.$resource);

        return $costs->implode(', ');
    }

    private function buildingLevelLabel(UserBuilding $building): string
    {
        if ($this->isRoad($building)) {
            return number_format($building->level).' km';
        }

        return 'Level '.number_format($building->level);
    }

    private function isMaxLevel(UserBuilding $building): bool
    {
        return $building->buildingType->max_level !== null
            && $building->level >= $building->buildingType->max_level;
    }

    /**
     * @return array<string, int>
     */
    private function upgradeCostsFor(UserBuilding $building, int $amount = 1): array
    {
        $type = $building->buildingType;
        $multiplier = (float) $type->upgrade_cost_multiplier;

        $costs = [];

        foreach ($type->base_costs ?? [] as $resource => $baseCost) {
            $costs[$resource] = $this->upgradeCostForResource(
                (int) $baseCost,
                $multiplier,
                $building->level,
                $amount,
            );
        }

        return $costs;
    }

    private function upgradeCostForResource(int $baseCost, float $multiplier, int $level, int $amount): int
    {
        if ($amount === 1) {
            return (int) ceil($baseCost * ($multiplier ** $level));
        }

        if ($multiplier === 1.0) {
            return $baseCost * $amount;
        }

        $total = $baseCost * ($multiplier ** $level) * (($multiplier ** $amount) - 1) / ($multiplier - 1);

        if (! is_finite($total) || $total > PHP_INT_MAX) {
            return PHP_INT_MAX;
        }

        return (int) ceil($total);
    }

    /**
     * @param  array<string, int>  $costs
     */
    private function canAfford(UserResource $resources, array $costs): bool
    {
        foreach ($costs as $resource => $cost) {
            if (! in_array($resource, ['gold', 'wood', 'stone', 'food'], true)) {
                return false;
            }

            if ($resources->{$resource} < $cost) {
                return false;
            }
        }

        return true;
    }

    private function baseProductionFor(BuildingType $buildingType): int
    {
        return (int) (
            $buildingType->getAttribute('base_production_per_hour')
            ?? $buildingType->getAttribute('base_production_per_minute')
            ?? 0
        );
    }

    private function isRoad(UserBuilding $building): bool
    {
        $type = $building->buildingType;

        return $type->slug === 'road'
            || strtolower($type->name) === 'road'
            || $type->effect_type === 'road_length'
            || $type->produces_resource === 'roadLength';
    }
}
