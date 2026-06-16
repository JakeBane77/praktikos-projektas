<?php

namespace App\Http\Controllers;

use App\Models\ResourceCollection;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBuilding;
use App\Models\UserResource;
use App\Models\WeatherSnapshot;
use App\Services\AchievementDashboardService;
use App\Services\AllianceDashboardService;
use App\Services\BuildingDashboardService;
use App\Services\LeaderboardDashboardService;
use App\Services\MinigameDashboardService;
use App\Services\ResourceProductionService;
use App\Support\MinigameStamina;
use App\Support\Weather;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const RESOURCE_TYPES = ['gold', 'wood', 'stone', 'food'];

    private const MAX_ROAD_BUILD_AMOUNT = 10_000_000;

    public function __construct(
        private readonly AchievementDashboardService $achievementDashboardService,
        private readonly AllianceDashboardService $allianceDashboardService,
        private readonly BuildingDashboardService $buildingDashboardService,
        private readonly LeaderboardDashboardService $leaderboardDashboardService,
        private readonly MinigameDashboardService $minigameDashboardService,
        private readonly ResourceProductionService $resourceProductionService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', $this->gameDataFor(
            $request->user(),
            $this->allianceSearchFor($request),
        ));
    }

    public function immersive(Request $request): Response
    {
        return Inertia::render('Immersive', $this->gameDataFor(
            $request->user(),
            $this->allianceSearchFor($request),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function gameDataFor(User $user, string $allianceSearch = ''): array
    {
        $resources = $this->resourceProductionService->resourcesFor($user);
        $buildings = $this->buildingDashboardService->buildingsFor($user);
        $productionBonuses = $this->achievementDashboardService->productionBonusesFor($user);
        $offlineProgress = $this->resourceProductionService->applyPassiveProduction($resources, $buildings, $productionBonuses);
        $achievements = $this->achievementDashboardService->syncFor($user, $resources, $buildings);
        $minigames = $this->minigameDashboardService->minigamesFor($user);
        $productionBonuses = $this->achievementDashboardService->productionBonusesFor($user);

        $productionRates = $this->resourceProductionService->productionRatesFor($buildings, $productionBonuses);
        $roadLength = $this->buildingDashboardService->roadLengthFor($buildings);
        $prestigeRoadRequirement = $this->buildingDashboardService->prestigeRoadRequirementFor($buildings);
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
            'canCollect' => $this->resourceProductionService->canCollect($resources),
            'offlineProgress' => $offlineProgress,
            'roadStats' => [
                'length' => $roadLength,
            ],
            'prestigeStats' => [
                'count' => (int) $resources->prestiges,
                'rank' => $this->prestigeLeaderboardRankFor((int) $resources->prestiges),
                'canPrestige' => $prestigeRoadRequirement > 0 && $roadLength >= $prestigeRoadRequirement,
                'requirement' => $prestigeRoadRequirement,
            ],
            'leaderboards' => $this->leaderboardDashboardService->cardsFor($user, $resources, $minigames),
            'alliances' => $this->allianceDashboardService->cardsFor($user, $allianceSearch),
            'achievementBonuses' => $this->achievementDashboardService->bonusCardsFor($productionBonuses),
            'achievementUnlocks' => $this->achievementDashboardService->unlockCardsFor($achievements),
            'minigames' => $this->minigameDashboardService->cardsFor($user, $minigames, $productionRates),
            'buildings' => $this->buildingDashboardService->cardsFor($buildings, $resources, $productionBonuses),
            'achievements' => $this->achievementDashboardService->cardsFor($achievements),
        ];
    }

    public function collect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $resources = $this->resourceProductionService->resourcesFor($user);
        Gate::authorize('collect', $resources);

        $buildings = $this->buildingDashboardService->buildingsFor($user);
        $productionBonuses = $this->achievementDashboardService->productionBonusesFor($user);
        $this->resourceProductionService->applyPassiveProduction($resources, $buildings, $productionBonuses);

        if (! $this->resourceProductionService->canCollect($resources)) {
            return redirect()
                ->to(url()->previous(route('dashboard')))
                ->withErrors([
                    'collect' => 'You have already collected resources today.',
                ]);
        }

        $collectAmounts = $this->resourceProductionService->collectAmountsFor($resources, $buildings, $productionBonuses);

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
        $this->achievementDashboardService->syncFor($user, $resources, $buildings);

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

        $resources = $this->resourceProductionService->resourcesFor($request->user());
        $buildings = $this->buildingDashboardService->buildingsFor($request->user());
        $this->resourceProductionService->applyPassiveProduction($resources, $buildings, $this->achievementDashboardService->productionBonusesFor($request->user()));
        $building->load('buildingType');
        $amount = $this->buildingDashboardService->isRoad($building) ? ($validated['amount'] ?? 1) : 1;

        if ($building->buildingType->max_level !== null && ($building->level + $amount) > $building->buildingType->max_level) {
            return redirect()
                ->to(url()->previous(route('dashboard')))
                ->withErrors([
                    'upgrade' => $building->buildingType->name.' is already at max level.',
                ]);
        }

        $costs = $this->buildingDashboardService->upgradeCostsFor($building, $amount);

        if (! $this->buildingDashboardService->canAfford($resources, $costs)) {
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

        $this->achievementDashboardService->syncFor($request->user(), $resources, $this->buildingDashboardService->buildingsFor($request->user()));

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function completeMinigame(Request $request, string $resource): RedirectResponse
    {
        if (! in_array($resource, self::RESOURCE_TYPES, true)) {
            abort(404);
        }

        $user = $request->user();
        $resources = $this->resourceProductionService->resourcesFor($user);
        $buildings = $this->buildingDashboardService->buildingsFor($user);
        $productionBonuses = $this->achievementDashboardService->productionBonusesFor($user);
        $this->resourceProductionService->applyPassiveProduction($resources, $buildings, $productionBonuses);
        $minigame = $this->minigameDashboardService->minigameFor($user, $resource);
        Gate::authorize('complete', $minigame);

        $stamina = $this->minigameDashboardService->staminaCardFor($user);

        if (! $stamina['isAvailable']) {
            return redirect()
                ->to(url()->previous(route('dashboard')))
                ->withErrors([
                    'minigame' => 'Minigame stamina is empty. Please wait before playing again.',
                ]);
        }

        $productionRates = $this->resourceProductionService->productionRatesFor($buildings, $productionBonuses);
        $reward = $this->minigameDashboardService->rewardFor((int) $productionRates[$resource]);
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
                'source' => MinigameStamina::sourceFor($resource),
                'collected_at' => now(),
            ]);
        });

        $this->achievementDashboardService->syncFor($user, $resources, $buildings);

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function prestige(Request $request): RedirectResponse
    {
        $user = $request->user();
        $resources = $this->resourceProductionService->resourcesFor($user);
        Gate::authorize('prestige', $resources);

        $buildings = $this->buildingDashboardService->buildingsFor($user);
        $this->resourceProductionService->applyPassiveProduction($resources, $buildings, $this->achievementDashboardService->productionBonusesFor($user));
        $prestigeRoadRequirement = $this->buildingDashboardService->prestigeRoadRequirementFor($buildings);

        if ($prestigeRoadRequirement <= 0) {
            return redirect()
                ->to(url()->previous(route('dashboard')))
                ->withErrors([
                    'prestige' => 'Road max level is not configured.',
                ]);
        }

        if ($this->buildingDashboardService->roadLengthFor($buildings) < $prestigeRoadRequirement) {
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

        $this->achievementDashboardService->syncFor($user, $resources->fresh(), $this->buildingDashboardService->buildingsFor($user));

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

    private function allianceSearchFor(Request $request): string
    {
        return str((string) $request->query('alliance_search', ''))
            ->trim()
            ->limit(80, '')
            ->toString();
    }

}
