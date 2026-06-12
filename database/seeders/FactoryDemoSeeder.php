<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Alliance;
use App\Models\AllianceApplication;
use App\Models\AllianceChatMessage;
use App\Models\AllianceCreationLog;
use App\Models\AllianceGoal;
use App\Models\AllianceGoalContribution;
use App\Models\AllianceMembership;
use App\Models\BuildingType;
use App\Models\Minigame;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBuilding;
use App\Models\UserResource;
use App\Models\WeatherSnapshot;
use Illuminate\Database\Eloquent\Collection;
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

        $users = User::factory()
            ->count(200)
            ->create();

        $users->each(function (User $user) use ($achievements, $buildingTypes): void {
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

        $this->seedAlliances($users);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function seedAlliances(Collection $users): void
    {
        $availableUsers = $users->shuffle()->values();
        $userIndex = 0;
        $allianceCount = min(12, intdiv($availableUsers->count(), 6));

        for ($allianceIndex = 0; $allianceIndex < $allianceCount; $allianceIndex++) {
            /** @var User|null $leader */
            $leader = $availableUsers->get($userIndex++);

            if (! $leader instanceof User) {
                break;
            }

            $alliance = Alliance::factory()
                ->for($leader, 'leader')
                ->state([
                    'is_open' => fake()->boolean(70),
                ])
                ->create();

            AllianceCreationLog::factory()
                ->for($leader)
                ->createdAt($alliance->created_at ?? now())
                ->create();

            AllianceMembership::factory()
                ->leader()
                ->for($alliance)
                ->for($leader)
                ->contributed(fake()->numberBetween(25_000, 500_000))
                ->create();

            $memberCount = fake()->numberBetween(3, 12);
            $members = collect();

            for ($memberIndex = 0; $memberIndex < $memberCount; $memberIndex++) {
                /** @var User|null $member */
                $member = $availableUsers->get($userIndex++);

                if (! $member instanceof User) {
                    break;
                }

                AllianceMembership::factory()
                    ->for($alliance)
                    ->for($member)
                    ->state([
                        'role' => $memberIndex < 2 && fake()->boolean(45) ? 'officer' : 'member',
                    ])
                    ->contributed(fake()->numberBetween(0, 300_000))
                    ->create();

                $members->push($member);
            }

            $currentGoal = AllianceGoal::factory()
                ->for($alliance)
                ->withProgress(fake()->numberBetween(0, 6_000_000))
                ->create();

            $previousGoal = AllianceGoal::factory()
                ->for($alliance)
                ->previousWeek()
                ->withProgress(fake()->numberBetween(0, 10_000_000))
                ->create();

            $allianceUsers = collect([$leader])->merge($members)->values();
            $donors = $allianceUsers->take(fake()->numberBetween(1, min(8, max(1, $allianceUsers->count()))));
            $chatUsers = $allianceUsers;

            for ($messageIndex = 0; $messageIndex < fake()->numberBetween(2, 10); $messageIndex++) {
                /** @var User $chatUser */
                $chatUser = $chatUsers->random();

                AllianceChatMessage::factory()
                    ->for($alliance)
                    ->for($chatUser)
                    ->create([
                        'created_at' => now()->subMinutes(fake()->numberBetween(1, 1_440)),
                    ]);
            }

            foreach ([$currentGoal, $previousGoal] as $goal) {
                $donors->each(function (User $donor) use ($goal): void {
                    AllianceGoalContribution::factory()
                        ->for($goal, 'goal')
                        ->for($donor)
                        ->forResource(fake()->randomElement(['gold', 'wood', 'stone', 'food']))
                        ->amount(fake()->numberBetween(1_000, 250_000))
                        ->create();
                });
            }

            if (! $alliance->is_open) {
                $applicationCount = fake()->numberBetween(0, 5);

                for ($applicationIndex = 0; $applicationIndex < $applicationCount; $applicationIndex++) {
                    /** @var User|null $applicant */
                    $applicant = $availableUsers->get($userIndex++);

                    if (! $applicant instanceof User) {
                        break;
                    }

                    AllianceApplication::factory()
                        ->for($alliance)
                        ->for($applicant)
                        ->create();
                }
            }
        }
    }
}
