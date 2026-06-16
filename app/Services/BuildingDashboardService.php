<?php

namespace App\Services;

use App\Models\BuildingType;
use App\Models\User;
use App\Models\UserBuilding;
use App\Models\UserResource;
use Illuminate\Database\Eloquent\Collection;

class BuildingDashboardService
{
    private const RESOURCE_TYPES = ['gold', 'wood', 'stone', 'food'];

    private const DEFAULT_PRODUCTION_BONUSES = [
        'all' => 0,
        'buildingTypes' => [],
    ];

    public function __construct(private readonly ResourceProductionService $resourceProductionService) {}

    /**
     * @return Collection<int, UserBuilding>
     */
    public function buildingsFor(User $user): Collection
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
     * @param  Collection<int, UserBuilding>  $buildings
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     * @return array<int, array<string, mixed>>
     */
    public function cardsFor(Collection $buildings, UserResource $resources, array $productionBonuses = self::DEFAULT_PRODUCTION_BONUSES): array
    {
        return $buildings->map(fn (UserBuilding $building): array => [
            'id' => $building->id,
            'name' => $building->buildingType->name,
            'level' => $building->level,
            'levelLabel' => $this->buildingLevelLabel($building),
            'description' => $this->buildingDescription($building),
            'production' => $this->buildingProductionLabel($building, $productionBonuses),
            'upgradeCost' => $this->upgradeCostLabel($building),
            'upgradeCosts' => $this->upgradeCostsFor($building),
            'baseCosts' => $this->baseCostsFor($building->buildingType),
            'upgradeCostMultiplier' => (float) $building->buildingType->upgrade_cost_multiplier,
            'maxLevel' => $building->buildingType->max_level,
            'isRoad' => $this->isRoad($building),
            'isMaxLevel' => $this->isMaxLevel($building),
            'canUpgrade' => ! $this->isMaxLevel($building)
                && $this->canAfford($resources, $this->upgradeCostsFor($building)),
        ])->values()->all();
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     */
    public function roadLengthFor(iterable $buildings): int
    {
        foreach ($buildings as $building) {
            if ($this->isRoad($building)) {
                return $building->level;
            }
        }

        return 0;
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     */
    public function prestigeRoadRequirementFor(iterable $buildings): int
    {
        foreach ($buildings as $building) {
            if ($this->isRoad($building)) {
                return (int) ($building->buildingType->max_level ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     */
    private function buildingProductionLabel(UserBuilding $building, array $productionBonuses = self::DEFAULT_PRODUCTION_BONUSES): string
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

        return '+'.number_format($this->resourceProductionService->productionFor($building, $productionBonuses)).' '.$resource.'/hour';
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

    /**
     * @return array<string, int>
     */
    private function baseCostsFor(BuildingType $buildingType): array
    {
        return collect($buildingType->base_costs ?? [])
            ->mapWithKeys(fn (mixed $amount, string $resource): array => [$resource => (int) $amount])
            ->all();
    }

    private function buildingLevelLabel(UserBuilding $building): string
    {
        if ($this->isRoad($building)) {
            return number_format($building->level).' km';
        }

        return 'Level '.number_format($building->level);
    }

    public function isMaxLevel(UserBuilding $building): bool
    {
        return $building->buildingType->max_level !== null
            && $building->level >= $building->buildingType->max_level;
    }

    /**
     * @return array<string, int>
     */
    public function upgradeCostsFor(UserBuilding $building, int $amount = 1): array
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
    public function canAfford(UserResource $resources, array $costs): bool
    {
        foreach ($costs as $resource => $cost) {
            if (! in_array($resource, self::RESOURCE_TYPES, true)) {
                return false;
            }

            if ($resources->{$resource} < $cost) {
                return false;
            }
        }

        return true;
    }

    public function isRoad(UserBuilding $building): bool
    {
        $type = $building->buildingType;

        return $type->slug === 'road'
            || strtolower($type->name) === 'road'
            || $type->effect_type === 'road_length'
            || $type->produces_resource === 'roadLength';
    }
}
