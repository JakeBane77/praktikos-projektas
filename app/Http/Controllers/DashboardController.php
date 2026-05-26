<?php

namespace App\Http\Controllers;

use App\Models\BuildingType;
use App\Models\ResourceCollection;
use App\Models\User;
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
        $this->applyPassiveProduction($resources, $buildings);

        $productionRates = $this->productionRatesFor($buildings);
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
            'buildings' => $buildings->map(fn (UserBuilding $building): array => [
                'id' => $building->id,
                'name' => $building->buildingType->name,
                'level' => $building->level,
                'levelLabel' => $this->buildingLevelLabel($building),
                'description' => $this->buildingDescription($building),
                'production' => $this->buildingProductionLabel($building),
                'upgradeCost' => $this->upgradeCostLabel($building),
                'isRoad' => $this->isRoad($building),
                'canUpgrade' => $this->canAfford($resources, $this->upgradeCostsFor($building)),
            ])->values(),
        ]);
    }

    public function collect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $resources = $this->resourcesFor($user);
        $buildings = $this->buildingsFor($user);
        $this->applyPassiveProduction($resources, $buildings);

        if (! $this->canCollect($resources)) {
            return redirect()
                ->route('dashboard')
                ->withErrors([
                    'collect' => 'You have already collected resources today.',
                ]);
        }

        $collectAmounts = $this->collectAmountsFor($buildings);

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
            'amount' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($building->user_id !== $request->user()->id) {
            abort(404);
        }

        $resources = $this->resourcesFor($request->user());
        $buildings = $this->buildingsFor($request->user());
        $this->applyPassiveProduction($resources, $buildings);
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
     * @return array{gold: int, wood: int, stone: int, food: int}
     */
    private function productionRatesFor(iterable $buildings): array
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

            $rates[$resource] += $this->productionFor($building);
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
     * @param  iterable<UserBuilding>  $buildings
     * @return array{gold: int, wood: int, stone: int, food: int}
     */
    private function collectAmountsFor(iterable $buildings): array
    {
        $rates = $this->productionRatesFor($buildings);

        return [
            'gold' => self::DAILY_BASE_REWARDS['gold'] + ($rates['gold'] * self::DAILY_PRODUCTION_HOURS),
            'wood' => self::DAILY_BASE_REWARDS['wood'] + ($rates['wood'] * self::DAILY_PRODUCTION_HOURS),
            'stone' => self::DAILY_BASE_REWARDS['stone'] + ($rates['stone'] * self::DAILY_PRODUCTION_HOURS),
            'food' => self::DAILY_BASE_REWARDS['food'] + ($rates['food'] * self::DAILY_PRODUCTION_HOURS),
        ];
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     */
    private function applyPassiveProduction(UserResource $resources, iterable $buildings): void
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

        $rates = $this->productionRatesFor($buildings);
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

    private function productionFor(UserBuilding $building): int
    {
        if ($building->level === 0) {
            return 0;
        }

        $type = $building->buildingType;
        $multiplier = (float) ($type->production_multiplier ?? 1);

        return (int) ceil($this->baseProductionFor($type) * ($multiplier ** ($building->level - 1)));
    }

    private function buildingProductionLabel(UserBuilding $building): string
    {
        if ($this->isRoad($building)) {
            return $building->level.' km built';
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

        return '+'.$this->productionFor($building).' '.$resource.'/hour';
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
            ->map(fn (int $amount, string $resource): string => $amount.' '.$resource);

        return $costs->implode(', ');
    }

    private function buildingLevelLabel(UserBuilding $building): string
    {
        if ($this->isRoad($building)) {
            return $building->level.' km';
        }

        return 'Level '.$building->level;
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
            $costs[$resource] = 0;

            for ($offset = 0; $offset < $amount; $offset++) {
                $costs[$resource] += (int) ceil($baseCost * ($multiplier ** ($building->level + $offset)));
            }
        }

        return $costs;
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
