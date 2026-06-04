<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\BuildingType;
use App\Models\Minigame;
use App\Models\ResourceCollection;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBuilding;
use App\Models\UserResource;
use App\Models\WeatherSnapshot;
use App\Support\Weather;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const RESOURCE_TYPES = ['gold', 'wood', 'stone', 'food'];

    private const RESOURCE_DISPLAY_ORDER = ['wood', 'food', 'stone', 'gold'];

    private const MAX_ROAD_BUILD_AMOUNT = 10_000_000;

    private const DAILY_BASE_REWARDS = [
        'gold' => 0,
        'wood' => 30,
        'stone' => 0,
        'food' => 20,
    ];

    private const DAILY_PRODUCTION_HOURS = 6;

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', $this->gameDataFor($request->user()));
    }

    public function immersive(Request $request): Response
    {
        return Inertia::render('Immersive', $this->gameDataFor($request->user()));
    }

    /**
     * @return array<string, mixed>
     */
    private function gameDataFor(User $user): array
    {
        $resources = $this->resourcesFor($user);
        $buildings = $this->buildingsFor($user);
        $productionBonuses = $this->productionBonusesFor($user);
        $this->applyPassiveProduction($resources, $buildings, $productionBonuses);
        $achievements = $this->syncAchievementsFor($user, $resources, $buildings);
        $minigames = $this->minigamesFor($user);
        $productionBonuses = $this->productionBonusesFor($user);

        $productionRates = $this->productionRatesFor($buildings, $productionBonuses);
        $roadLength = $this->roadLengthFor($buildings);
        $prestigeRoadRequirement = $this->prestigeRoadRequirementFor($buildings);
        $serverTime = now();

        return [
            'serverTime' => [
                'iso' => $serverTime->toIso8601String(),
                'timezone' => $serverTime->timezoneName,
            ],
            'weather' => $this->weatherSnapshotCard($user),
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
            ],
            'prestigeStats' => [
                'count' => (int) $resources->prestiges,
                'rank' => $this->prestigeLeaderboardRankFor((int) $resources->prestiges),
                'canPrestige' => $prestigeRoadRequirement > 0 && $roadLength >= $prestigeRoadRequirement,
                'requirement' => $prestigeRoadRequirement,
            ],
            'leaderboards' => $this->leaderboardCardsFor($user, $resources, $minigames),
            'achievementBonuses' => $this->achievementBonusCardsFor($productionBonuses),
            'achievementUnlocks' => $this->achievementUnlockCardsFor($achievements),
            'minigames' => $this->minigameCardsFor($minigames, $productionRates),
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
        ];
    }

    public function collect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $resources = $this->resourcesFor($user);
        Gate::authorize('collect', $resources);

        $buildings = $this->buildingsFor($user);
        $productionBonuses = $this->productionBonusesFor($user);
        $this->applyPassiveProduction($resources, $buildings, $productionBonuses);

        if (! $this->canCollect($resources)) {
            return redirect()
                ->to(url()->previous(route('dashboard')))
                ->withErrors([
                    'collect' => 'You have already collected resources today.',
                ]);
        }

        $collectAmounts = $this->collectAmountsFor($resources, $buildings, $productionBonuses);

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

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function upgrade(Request $request, UserBuilding $building): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_ROAD_BUILD_AMOUNT],
        ]);

        Gate::authorize('upgrade', $building);

        $resources = $this->resourcesFor($request->user());
        $buildings = $this->buildingsFor($request->user());
        $this->applyPassiveProduction($resources, $buildings, $this->productionBonusesFor($request->user()));
        $building->load('buildingType');
        $amount = $this->isRoad($building) ? ($validated['amount'] ?? 1) : 1;

        if ($building->buildingType->max_level !== null && ($building->level + $amount) > $building->buildingType->max_level) {
            return redirect()
                ->to(url()->previous(route('dashboard')))
                ->withErrors([
                    'upgrade' => $building->buildingType->name.' is already at max level.',
                ]);
        }

        $costs = $this->upgradeCostsFor($building, $amount);

        if (! $this->canAfford($resources, $costs)) {
            return redirect()
                ->to(url()->previous(route('dashboard')))
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

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function completeMinigame(Request $request, string $resource): RedirectResponse
    {
        if (! in_array($resource, self::RESOURCE_TYPES, true)) {
            abort(404);
        }

        $user = $request->user();
        $resources = $this->resourcesFor($user);
        $buildings = $this->buildingsFor($user);
        $productionBonuses = $this->productionBonusesFor($user);
        $this->applyPassiveProduction($resources, $buildings, $productionBonuses);
        $minigame = $this->minigameFor($user, $resource);
        Gate::authorize('complete', $minigame);

        $productionRates = $this->productionRatesFor($buildings, $productionBonuses);
        $reward = $this->minigameRewardFor((int) $productionRates[$resource]);
        $lifetimeColumn = 'lifetime_'.$resource;

        DB::transaction(function () use ($lifetimeColumn, $minigame, $resource, $resources, $reward): void {
            $resources->{$resource} += $reward;
            $resources->{$lifetimeColumn} += $reward;
            $resources->save();

            $minigame->completions += 1;
            $minigame->resources_gained += $reward;
            $minigame->save();

            ResourceCollection::create([
                'user_id' => $resources->user_id,
                'gold' => $resource === 'gold' ? $reward : 0,
                'wood' => $resource === 'wood' ? $reward : 0,
                'stone' => $resource === 'stone' ? $reward : 0,
                'food' => $resource === 'food' ? $reward : 0,
                'source' => 'minigame_'.$resource,
                'collected_at' => now(),
            ]);
        });

        $this->syncAchievementsFor($user, $resources, $buildings);

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function prestige(Request $request): RedirectResponse
    {
        $user = $request->user();
        $resources = $this->resourcesFor($user);
        Gate::authorize('prestige', $resources);

        $buildings = $this->buildingsFor($user);
        $this->applyPassiveProduction($resources, $buildings, $this->productionBonusesFor($user));
        $prestigeRoadRequirement = $this->prestigeRoadRequirementFor($buildings);

        if ($prestigeRoadRequirement <= 0) {
            return redirect()
                ->to(url()->previous(route('dashboard')))
                ->withErrors([
                    'prestige' => 'Road max level is not configured.',
                ]);
        }

        if ($this->roadLengthFor($buildings) < $prestigeRoadRequirement) {
            return redirect()
                ->to(url()->previous(route('dashboard')))
                ->withErrors([
                    'prestige' => 'You need '.number_format($prestigeRoadRequirement).' km of roads before you can prestige.',
                ]);
        }

        DB::transaction(function () use ($resources, $user): void {
            $resources->gold = 0;
            $resources->wood = 0;
            $resources->stone = 0;
            $resources->food = 0;
            $resources->prestiges += 1;
            $resources->last_produced_at = now();
            $resources->last_collected_at = null;
            $resources->save();

            UserBuilding::query()
                ->where('user_id', $user->id)
                ->update([
                    'level' => 0,
                    'built_at' => null,
                ]);
        });

        $this->syncAchievementsFor($user, $resources->fresh(), $this->buildingsFor($user));

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function markAchievementUnlocksSeen(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $achievements = UserAchievement::query()
            ->whereIn('id', $validated['ids'])
            ->get();

        $achievements->each(fn (UserAchievement $achievement) => Gate::authorize('markUnlockSeen', $achievement));

        UserAchievement::query()
            ->whereKey($achievements->modelKeys())
            ->whereNotNull('unlocked_at')
            ->whereNull('notification_seen_at')
            ->update([
                'notification_seen_at' => now(),
            ]);

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function updateWeatherLocation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'weather_code' => ['required', 'integer', 'between:0,99'],
            'api_time' => ['required', 'date'],
        ]);

        $latitude = Weather::normalizeCoordinate($validated['latitude']);
        $longitude = Weather::normalizeCoordinate($validated['longitude']);
        $snapshot = WeatherSnapshot::query()
            ->firstOrNew([
                'user_id' => $request->user()->id,
            ]);

        Gate::authorize($snapshot->exists ? 'update' : 'create', $snapshot->exists ? $snapshot : WeatherSnapshot::class);

        $snapshot->fill([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'weather_code' => (int) $validated['weather_code'],
            'api_time' => CarbonImmutable::parse($validated['api_time'], 'UTC')
                ->setTimezone(config('app.timezone')),
        ])->save();

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function resetWeatherLocation(Request $request): RedirectResponse
    {
        $snapshot = WeatherSnapshot::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($snapshot !== null) {
            Gate::authorize('delete', $snapshot);
            $snapshot->delete();
        }

        return redirect()->to(url()->previous(route('dashboard')));
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
                'prestiges' => 0,
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
     * @return Collection<int, Minigame>
     */
    private function minigamesFor(User $user): Collection
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

    private function minigameFor(User $user, string $resource): Minigame
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

    /**
     * @param  iterable<UserBuilding>  $buildings
     */
    private function prestigeRoadRequirementFor(iterable $buildings): int
    {
        foreach ($buildings as $building) {
            if ($this->isRoad($building)) {
                return (int) ($building->buildingType->max_level ?? 0);
            }
        }

        return 0;
    }

    private function prestigeLeaderboardRankFor(int $prestiges): int
    {
        return UserResource::query()
            ->where('prestiges', '>', $prestiges)
            ->count() + 1;
    }

    /**
     * @return array{latitude: float, longitude: float, weatherCode: int|null, conditions: array{clear: bool, cloudy: bool, raining: bool, foggy: bool, thunderstorm: bool, snowing: bool}, apiTime: string|null, apiTimeIso: string|null, updatedAt: string|null, updatedAtIso: string|null}
     */
    private function weatherSnapshotCard(User $user): array
    {
        $userSnapshot = WeatherSnapshot::query()
            ->where('user_id', $user->id)
            ->first();
        $snapshot = $userSnapshot ?? WeatherSnapshot::query()
            ->whereNull('user_id')
            ->where('latitude', Weather::LATITUDE)
            ->where('longitude', Weather::LONGITUDE)
            ->first();
        $latitude = $userSnapshot
            ? Weather::normalizeCoordinate($userSnapshot->latitude)
            : Weather::LATITUDE;
        $longitude = $userSnapshot
            ? Weather::normalizeCoordinate($userSnapshot->longitude)
            : Weather::LONGITUDE;

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'isUsingGeolocation' => $userSnapshot !== null,
            'locationUpdatedAt' => $userSnapshot?->updated_at?->format('Y-m-d H:i'),
            'weatherCode' => $snapshot?->weather_code,
            'conditions' => Weather::conditionsFor($snapshot?->weather_code),
            'apiTime' => $snapshot?->api_time?->format('Y-m-d H:i'),
            'apiTimeIso' => $snapshot?->api_time?->toIso8601String(),
            'updatedAt' => $snapshot?->updated_at?->format('Y-m-d H:i'),
            'updatedAtIso' => $snapshot?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, Minigame>  $minigames
     * @return array{defaultKey: string, boards: array<int, array<string, mixed>>}
     */
    private function leaderboardCardsFor(User $user, UserResource $resources, Collection $minigames): array
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
            $currentValue = (int) ($minigames->firstWhere('resource', $resource)?->completions ?? 0);
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
     * @param  Collection<int, UserResource|Minigame>  $rows
     * @return array<int, array{rank: int, userId: int, userName: string, value: int, valueLabel: string, isCurrentUser: bool}>
     */
    private function leaderboardEntriesFor(Collection $rows, string $valueColumn, User $currentUser): array
    {
        $rank = 0;
        $position = 0;
        $previousValue = null;

        return $rows
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
                    'userName' => $row->user?->name ?? 'Unknown player',
                    'value' => $value,
                    'valueLabel' => number_format($value),
                    'isCurrentUser' => (int) $row->user_id === (int) $currentUser->id,
                ];
            })
            ->values()
            ->all();
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
            'minigame_completions' => $this->minigameCompletionProgressFor($resources->user, $achievement->resource_type),
            'prestiges' => $resources->prestiges,
            'building_level' => $this->buildingLevelProgressFor($achievement, $buildings),
            'building_levels', 'total_building_levels', 'buildings_built' => $buildings->sum('level'),
            'road_length' => $this->roadLengthFor($buildings),
            default => 0,
        };
    }

    private function resourceProgressFor(UserResource $resources, ?string $resourceType, bool $lifetime): int
    {
        if (! in_array($resourceType, self::RESOURCE_TYPES, true)) {
            return 0;
        }

        $column = $lifetime ? 'lifetime_'.$resourceType : $resourceType;

        return (int) $resources->{$column};
    }

    private function minigameCompletionProgressFor(User $user, ?string $resourceType): int
    {
        if (! in_array($resourceType, self::RESOURCE_TYPES, true)) {
            return 0;
        }

        return (int) Minigame::query()
            ->where('user_id', $user->id)
            ->where('resource', $resourceType)
            ->value('completions');
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
        $rewardLabel = '+'.$bonusPercent.'% '.$target.' base production';

        if ($achievement->slug === 'prestiges-1') {
            return $rewardLabel.', daily collect base becomes 100 of each resource';
        }

        return $rewardLabel;
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
     * @param  Collection<int, Minigame>  $minigames
     * @param  array{gold: int, wood: int, stone: int, food: int}  $productionRates
     * @return array<int, array{resource: string, label: string, currentProduction: int, reward: int, rewardLabel: string, completions: int, resourcesGained: int}>
     */
    private function minigameCardsFor(Collection $minigames, array $productionRates): array
    {
        $minigamesByResource = $minigames->keyBy('resource');

        return collect(self::RESOURCE_DISPLAY_ORDER)
            ->map(function (string $resource) use ($minigamesByResource, $productionRates): array {
                $currentProduction = (int) $productionRates[$resource];
                $reward = $this->minigameRewardFor($currentProduction);
                $minigame = $minigamesByResource->get($resource);

                return [
                    'resource' => $resource,
                    'label' => str($resource)->title()->append(' minigame')->toString(),
                    'currentProduction' => $currentProduction,
                    'reward' => $reward,
                    'rewardLabel' => '+'.number_format($reward).' '.$resource,
                    'completions' => (int) ($minigame?->completions ?? 0),
                    'resourcesGained' => (int) ($minigame?->resources_gained ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function minigameRewardFor(int $currentProduction): int
    {
        return (int) ceil(1 + ($currentProduction * 0.02));
    }

    /**
     * @param  array{all: int, buildingTypes: array<int, int>}  $productionBonuses
     * @return array{gold: int, wood: int, stone: int, food: int}
     */
    private function collectAmountsFor(UserResource $resources, iterable $buildings, array $productionBonuses = []): array
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
            if (! in_array($resource, self::RESOURCE_TYPES, true)) {
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
