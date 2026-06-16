<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\BuildingType;
use App\Models\Minigame;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBuilding;
use App\Models\UserResource;
use Illuminate\Database\Eloquent\Collection;

class AchievementDashboardService
{
    private const RESOURCE_TYPES = ['gold', 'wood', 'stone', 'food'];

    private const DEFAULT_PRODUCTION_BONUSES = [
        'all' => 0,
        'buildingTypes' => [],
    ];

    public function __construct(private readonly AllianceGoalService $allianceGoalService) {}

    /**
     * @return array{all: int, buildingTypes: array<int, int>}
     */
    public function productionBonusesFor(User $user): array
    {
        $bonuses = self::DEFAULT_PRODUCTION_BONUSES;

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

        $allianceBonusPercent = $this->allianceGoalService->activeBonusPercentFor($user);

        if ($allianceBonusPercent > 0) {
            $bonuses['all'] += $allianceBonusPercent;
        }

        return $bonuses;
    }

    /**
     * @param  Collection<int, UserBuilding>  $buildings
     * @return Collection<int, UserAchievement>
     */
    public function syncFor(User $user, UserResource $resources, Collection $buildings): Collection
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
            'manual_collects' => (int) $resources->manual_collects,
            'minigame_completions' => $this->minigameCompletionProgressFor($resources->user, $achievement->resource_type),
            'prestiges' => (int) $resources->prestiges,
            'building_level' => $this->buildingLevelProgressFor($achievement, $buildings),
            'building_levels', 'total_building_levels', 'buildings_built' => (int) $buildings->sum('level'),
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

        $building = $buildings->firstWhere('building_type_id', $achievement->building_type_id);

        return $building instanceof UserBuilding ? (int) $building->level : 0;
    }

    /**
     * @param  iterable<UserBuilding>  $buildings
     */
    private function roadLengthFor(iterable $buildings): int
    {
        return collect($buildings)
            ->filter(fn (UserBuilding $building): bool => $this->isRoad($building))
            ->sum(fn (UserBuilding $building): int => (int) $building->level);
    }

    private function isRoad(UserBuilding $building): bool
    {
        $type = $building->buildingType;

        return $type->slug === 'road'
            || strtolower($type->name) === 'road'
            || $type->effect_type === 'road_length'
            || $type->produces_resource === 'roadLength';
    }

    /**
     * @param  Collection<int, UserAchievement>  $userAchievements
     * @return array<int, array<string, mixed>>
     */
    public function cardsFor(Collection $userAchievements): array
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
                    'rewardLabel' => $this->rewardLabel($achievement),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, UserAchievement>  $userAchievements
     * @return array<int, array<string, mixed>>
     */
    public function unlockCardsFor(Collection $userAchievements): array
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
                    'rewardLabel' => $this->rewardLabel($achievement),
                ];
            })
            ->values()
            ->all();
    }

    public function rewardLabel(Achievement $achievement): string
    {
        $bonusPercent = (int) $achievement->production_bonus_percent;

        if ($bonusPercent <= 0) {
            return 'No production bonus';
        }

        $bonusBuildingType = $achievement->bonusBuildingType;
        $target = $bonusBuildingType instanceof BuildingType ? $bonusBuildingType->name : 'all buildings';
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
    public function bonusCardsFor(array $productionBonuses): array
    {
        $cards = [];
        $allBonus = (int) $productionBonuses['all'];

        if ($allBonus > 0) {
            $cards[] = [
                'id' => 'all',
                'label' => 'All buildings',
                'bonusPercent' => $allBonus,
                'bonusLabel' => '+'.number_format($allBonus).'%',
            ];
        }

        $buildingTypeBonuses = $productionBonuses['buildingTypes'];
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
}
