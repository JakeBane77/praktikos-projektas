<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\BuildingType;
use App\Models\Minigame;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBuilding;
use App\Models\UserResource;
use App\Models\WeatherSnapshot;
use Illuminate\Database\Seeder;

class FactoryDemoSeeder extends Seeder
{
    /**
     * Seed demo users and game state through factories.
     */
    public function run(): void
    {
        $this->call([
            BuildingTypeSeeder::class,
            AchievementSeeder::class,
        ]);

        $buildingTypes = BuildingType::query()
            ->orderBy('id')
            ->get();
        $achievements = Achievement::query()
            ->orderBy('id')
            ->get();

        User::factory()
            ->count(200)
            ->create()
            ->each(function (User $user) use ($achievements, $buildingTypes): void {
                UserResource::factory()
                    ->for($user)
                    ->withResources(
                        gold: fake()->numberBetween(0, 250_000),
                        wood: fake()->numberBetween(0, 250_000),
                        stone: fake()->numberBetween(0, 250_000),
                        food: fake()->numberBetween(0, 250_000),
                    )
                    ->withLifetime(
                        gold: fake()->numberBetween(250_000, 2_000_000),
                        wood: fake()->numberBetween(250_000, 2_000_000),
                        stone: fake()->numberBetween(250_000, 2_000_000),
                        food: fake()->numberBetween(250_000, 2_000_000),
                    )
                    ->create([
                        'manual_collects' => fake()->numberBetween(0, 1_500),
                        'prestiges' => fake()->numberBetween(0, 25),
                        'last_produced_at' => now()->subMinutes(fake()->numberBetween(0, 360)),
                        'last_collected_at' => fake()->boolean(60) ? now()->subHours(fake()->numberBetween(0, 48)) : null,
                    ]);

                $buildingTypes->each(function (BuildingType $buildingType) use ($user): void {
                    $maxLevel = (int) ($buildingType->max_level ?? 25);
                    $level = $buildingType->slug === 'road'
                        ? fake()->numberBetween(0, min($maxLevel, 10_000_000))
                        : fake()->numberBetween(0, min($maxLevel, 25));

                    UserBuilding::factory()
                        ->for($user)
                        ->for($buildingType, 'buildingType')
                        ->level($level)
                        ->create();
                });

                foreach (['wood', 'food', 'stone', 'gold'] as $resource) {
                    Minigame::factory()
                        ->for($user)
                        ->resource($resource)
                        ->completed(
                            completions: fake()->numberBetween(0, 1_500),
                            resourcesGained: fake()->numberBetween(0, 250_000),
                        )
                        ->create();
                }

                $achievementCount = min(fake()->numberBetween(0, 8), $achievements->count());

                if ($achievementCount > 0) {
                    $achievements
                        ->random($achievementCount)
                        ->each(function (Achievement $achievement) use ($user): void {
                            $progress = fake()->numberBetween(0, (int) $achievement->target_value);

                            UserAchievement::factory()
                                ->for($user)
                                ->for($achievement)
                                ->create([
                                    'progress' => $progress,
                                    'unlocked_at' => $progress >= $achievement->target_value ? now() : null,
                                    'notification_seen_at' => $progress >= $achievement->target_value && fake()->boolean(70) ? now() : null,
                                ]);
                        });
                }

                if (fake()->boolean(35)) {
                    WeatherSnapshot::factory()
                        ->for($user)
                        ->create();
                }
            });
    }
}
