<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AchievementSeeder extends Seeder
{
    private const DEFAULT_PRODUCTION_BONUS_PERCENT = 5;

    /**
     * Seed the achievements table.
     */
    public function run(): void
    {
        $now = now();
        $buildingTypes = DB::table('building_types')
            ->select('id', 'name', 'slug', 'produces_resource')
            ->orderBy('id')
            ->get();
        $producerIdsByResource = $buildingTypes
            ->whereIn('produces_resource', ['gold', 'wood', 'stone', 'food'])
            ->pluck('id', 'produces_resource');

        $achievements = [
            ...$this->resourceAchievements($now, $producerIdsByResource),
            ...$this->buildingLevelAchievements($now, $buildingTypes),
            ...$this->roadAchievements($now),
            ...$this->manualCollectAchievements($now),
            ...$this->minigameAchievements($now, $producerIdsByResource),
            ...$this->prestigeAchievements($now),
        ];

        DB::table('achievements')->upsert(
            $achievements,
            ['slug'],
            [
                'building_type_id',
                'name',
                'description',
                'type',
                'resource_type',
                'target_value',
                'reward_gold',
                'reward_wood',
                'reward_stone',
                'reward_food',
                'production_bonus_percent',
                'bonus_building_type_id',
                'updated_at',
            ],
        );
    }

    private function resourceAchievements(mixed $now, mixed $producerIdsByResource): array
    {
        $rows = [];
        $targets = [1_000, 100_000, 1_000_000];

        foreach (['gold', 'wood', 'stone', 'food'] as $resource) {
            foreach ($targets as $target) {
                $resourceName = Str::title($resource);
                $formattedTarget = number_format($target);

                $rows[] = $this->achievementRow(
                    name: $formattedTarget.' '.$resourceName,
                    slug: $resource.'-'.$target,
                    description: 'Collect '.$formattedTarget.' lifetime '.$resource.'.',
                    type: 'resource_lifetime',
                    targetValue: $target,
                    now: $now,
                    resourceType: $resource,
                    bonusBuildingTypeId: $producerIdsByResource[$resource] ?? null,
                );
            }
        }

        return $rows;
    }

    private function buildingLevelAchievements(mixed $now, mixed $buildingTypes): array
    {
        $rows = [];
        $targets = [5, 10, 15, 20, 25];

        foreach ($buildingTypes->whereNotIn('slug', ['road']) as $buildingType) {
            foreach ($targets as $target) {
                $rows[] = $this->achievementRow(
                    name: $buildingType->name.' Level '.$target,
                    slug: $buildingType->slug.'-level-'.$target,
                    description: 'Upgrade '.$buildingType->name.' to level '.$target.' on the path to maxing it out.',
                    type: 'building_level',
                    targetValue: $target,
                    now: $now,
                    buildingTypeId: $buildingType->id,
                    bonusBuildingTypeId: $buildingType->id,
                );
            }
        }

        return $rows;
    }

    private function roadAchievements(mixed $now): array
    {
        $rows = [];
        $targets = [100, 1_000, 10_000, 100_000, 1_000_000, 10_000_000];

        foreach ($targets as $target) {
            $formattedTarget = number_format($target);

            $rows[] = $this->achievementRow(
                name: $formattedTarget.' km Road Network',
                slug: 'road-'.$target.'-km',
                description: 'Build '.$formattedTarget.' km of roads.',
                type: 'road_length',
                targetValue: $target,
                now: $now,
            );
        }

        $rows[] = $this->achievementRow(
            name: 'Connect the whole planet',
            slug: 'connect-the-whole-planet',
            description: 'You have connected the entire planet with roads',
            type: 'road_length',
            targetValue: 40_000_000,
            now: $now,
        );

        return $rows;
    }

    private function manualCollectAchievements(mixed $now): array
    {
        return [
            $this->achievementRow(
                name: 'Dedicated Collector',
                slug: 'manual-collects-10',
                description: 'Collect manually 10 times. Your dedication to checking in is starting to shape the settlement.',
                type: 'manual_collects',
                targetValue: 10,
                now: $now,
            ),
            $this->achievementRow(
                name: 'Steady Steward',
                slug: 'manual-collects-100',
                description: 'Collect manually 100 times. Your dedication to the game is becoming a real habit.',
                type: 'manual_collects',
                targetValue: 100,
                now: $now,
            ),
            $this->achievementRow(
                name: 'Unshakable Founder',
                slug: 'manual-collects-1000',
                description: 'Collect manually 1,000 times. Your dedication to the game is impossible to miss.',
                type: 'manual_collects',
                targetValue: 1_000,
                now: $now,
            ),
        ];
    }

    private function minigameAchievements(mixed $now, mixed $producerIdsByResource): array
    {
        $rows = [];

        foreach (['wood', 'food', 'stone', 'gold'] as $resource) {
            $resourceName = Str::title($resource);

            foreach ([10, 100, 1_000] as $target) {
                $formattedTarget = number_format($target);

                $rows[] = $this->achievementRow(
                    name: $resourceName.' Minigame '.$formattedTarget,
                    slug: $resource.'-minigame-completions-'.$target,
                    description: 'Complete the '.$resource.' minigame '.$formattedTarget.' times.',
                    type: 'minigame_completions',
                    targetValue: $target,
                    now: $now,
                    resourceType: $resource,
                    bonusBuildingTypeId: $producerIdsByResource[$resource] ?? null,
                );
            }
        }

        return $rows;
    }

    private function prestigeAchievements(mixed $now): array
    {
        $rows = [];

        foreach ([1, 5, 10, 100] as $target) {
            $rows[] = $this->achievementRow(
                name: number_format($target).' Prestige'.($target === 1 ? '' : 's'),
                slug: 'prestiges-'.$target,
                description: $target === 1
                    ? 'Prestige 1 time and begin again with your achievements intact. Daily collect now gives 100 of each resource.'
                    : 'Prestige '.number_format($target).' times and begin again with your achievements intact.',
                type: 'prestiges',
                targetValue: $target,
                now: $now,
            );
        }

        return $rows;
    }

    private function achievementRow(
        string $name,
        string $slug,
        string $description,
        string $type,
        int $targetValue,
        mixed $now,
        ?int $buildingTypeId = null,
        ?string $resourceType = null,
        ?int $bonusBuildingTypeId = null,
    ): array {
        return [
            'building_type_id' => $buildingTypeId,
            'bonus_building_type_id' => $bonusBuildingTypeId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'type' => $type,
            'resource_type' => $resourceType,
            'target_value' => $targetValue,
            'reward_gold' => 0,
            'reward_wood' => 0,
            'reward_stone' => 0,
            'reward_food' => 0,
            'production_bonus_percent' => self::DEFAULT_PRODUCTION_BONUS_PERCENT,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
