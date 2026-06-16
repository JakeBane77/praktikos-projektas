<?php

namespace App\Services;

use App\Models\BuildingType;
use App\Models\ResourceCollection;
use App\Models\User;
use App\Models\UserBuilding;
use App\Models\UserResource;

class ResourceProductionService
{
    private const DAILY_BASE_REWARDS = [
        'gold' => 0,
        'wood' => 30,
        'stone' => 0,
        'food' => 20,
    ];

    private const DAILY_PRODUCTION_HOURS = 6;

    private const DEFAULT_PRODUCTION_BONUSES = [
        'all' => 0,
        'buildingTypes' => [],
    ];

    public function resourcesFor(User $user): UserResource
    {
        return UserResource::firstOrCreate(
            ['user_id' => $user->id],
            [
                'gold' => 0,
                'wood' => 0,
                'stone' => 0,
                'food' => 0,
                'lifetime_gold' => 0,
                'lifetime_wood' => 0,
                'lifetime_stone' => 0,
                'lifetime_food' => 0,
                'manual_collects' => 0,
                'prestiges' => 0,
                'last_produced_at' => now(),
            ],
        );
    }

    public function canCollect(UserResource $resources): bool
    {
        return $resources->last_collected_at === null
            || $resources->last_collected_at->copy()->startOfDay()->lt(today());
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     * @return array{gold: int, wood: int, stone: int, food: int}
     */
    public function productionRatesFor(iterable $buildings, array $productionBonuses = self::DEFAULT_PRODUCTION_BONUSES): array
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
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     * @return array{gold: int, wood: int, stone: int, food: int}
     */
    public function collectAmountsFor(UserResource $resources, iterable $buildings, array $productionBonuses = self::DEFAULT_PRODUCTION_BONUSES): array
    {
        $rates = $this->productionRatesFor($buildings, $productionBonuses);
        $baseRewards = $this->dailyBaseRewardsFor($resources);

        return [
            'gold' => $baseRewards['gold'] + ($rates['gold'] * self::DAILY_PRODUCTION_HOURS),
            'wood' => $baseRewards['wood'] + ($rates['wood'] * self::DAILY_PRODUCTION_HOURS),
            'stone' => $baseRewards['stone'] + ($rates['stone'] * self::DAILY_PRODUCTION_HOURS),
            'food' => $baseRewards['food'] + ($rates['food'] * self::DAILY_PRODUCTION_HOURS),
        ];
    }

    /**
     * @return array{gold: int, wood: int, stone: int, food: int}
     */
    private function dailyBaseRewardsFor(UserResource $resources): array
    {
        if ((int) $resources->prestiges < 1) {
            return self::DAILY_BASE_REWARDS;
        }

        return [
            'gold' => 100,
            'wood' => 100,
            'stone' => 100,
            'food' => 100,
        ];
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     * @return array{elapsedHours: int, resources: array{gold: int, wood: int, stone: int, food: int}, total: int}|null
     */
    public function applyPassiveProduction(UserResource $resources, iterable $buildings, array $productionBonuses = self::DEFAULT_PRODUCTION_BONUSES): ?array
    {
        if ($resources->last_produced_at === null) {
            $resources->last_produced_at = now();
            $resources->save();

            return null;
        }

        $elapsedHours = (int) $resources->last_produced_at->diffInHours(now());

        if ($elapsedHours <= 0) {
            return null;
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

        $totalAmount = (int) array_sum($amounts);

        if ($totalAmount > 0) {
            ResourceCollection::create([
                'user_id' => $resources->user_id,
                'gold' => $amounts['gold'],
                'wood' => $amounts['wood'],
                'stone' => $amounts['stone'],
                'food' => $amounts['food'],
                'source' => 'passive',
                'collected_at' => now(),
            ]);

            return [
                'elapsedHours' => $elapsedHours,
                'resources' => $amounts,
                'total' => $totalAmount,
            ];
        }

        return null;
    }

    /**
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     */
    public function productionFor(UserBuilding $building, array $productionBonuses = self::DEFAULT_PRODUCTION_BONUSES): int
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
     * @param  array{all?: int, buildingTypes?: array<int, int>}  $productionBonuses
     */
    private function productionBonusPercentFor(BuildingType $buildingType, array $productionBonuses): int
    {
        return (int) ($productionBonuses['all'] ?? 0)
            + (int) ($productionBonuses['buildingTypes'][$buildingType->id] ?? 0);
    }

    private function baseProductionFor(BuildingType $buildingType): int
    {
        return (int) $buildingType->getAttribute('base_production_per_hour');
    }
}
