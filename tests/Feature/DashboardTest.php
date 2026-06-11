<?php

use App\Models\Achievement;
use App\Models\Alliance;
use App\Models\AllianceGoal;
use App\Models\AllianceGoalContribution;
use App\Models\AllianceMembership;
use App\Models\BuildingType;
use App\Models\Minigame;
use App\Models\ResourceCollection;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBuilding;
use App\Models\UserResource;
use App\Models\WeatherSnapshot;
use App\Services\AllianceGoalService;
use App\Support\MinigameStamina;
use App\Support\Weather;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('new users can visit the dashboard when counter achievements exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Achievement::create([
        'name' => 'First Counter Collect',
        'slug' => 'first-counter-collect',
        'description' => 'Collect manually once.',
        'type' => 'manual_collects',
        'target_value' => 1,
    ]);

    Achievement::create([
        'name' => 'First Counter Prestige',
        'slug' => 'first-counter-prestige',
        'description' => 'Prestige once.',
        'type' => 'prestiges',
        'target_value' => 1,
    ]);

    $this->get(route('dashboard'))
        ->assertOk();

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'manual_collects' => 0,
        'prestiges' => 0,
    ]);
});

test('authenticated users can visit immersive mode with game data', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('immersive'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Immersive')
            ->has('weather')
            ->has('buildings')
            ->has('roadStats')
            ->has('serverTime')
        );
});

test('dashboard exposes other alliance members and contributions while user has an alliance', function () {
    $user = User::factory()->create();
    $currentLeader = User::factory()->create();
    $otherLeader = User::factory()->create(['name' => 'Other Leader']);
    $otherMember = User::factory()->create(['name' => 'Other Member']);
    $this->actingAs($user);

    $currentAlliance = Alliance::create([
        'name' => 'Current Alliance',
        'slug' => 'current-alliance',
        'leader_id' => $currentLeader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $currentAlliance->id,
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $otherAlliance = Alliance::create([
        'name' => 'Other Alliance',
        'slug' => 'other-alliance',
        'leader_id' => $otherLeader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $otherAlliance->id,
        'user_id' => $otherLeader->id,
        'role' => 'leader',
        'total_contributed' => 500,
        'joined_at' => now(),
    ]);

    AllianceMembership::create([
        'alliance_id' => $otherAlliance->id,
        'user_id' => $otherMember->id,
        'role' => 'member',
        'total_contributed' => 1_000,
        'joined_at' => now(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alliances.current.id', $currentAlliance->id)
            ->where('alliances.available.0.id', $otherAlliance->id)
            ->where('alliances.available.0.canJoin', false)
            ->where('alliances.available.0.members.0.userId', $otherLeader->id)
            ->where('alliances.available.0.members.0.role', 'leader')
            ->where('alliances.available.0.members.0.totalContributed', 500)
            ->where('alliances.available.0.members.1.userId', $otherMember->id)
            ->where('alliances.available.0.members.1.totalContributed', 1_000)
        );
});

test('dashboard alliance search filters available alliances by alliance name only', function () {
    $user = User::factory()->create();
    $matchingLeader = User::factory()->create(['name' => 'Plain Leader']);
    $leaderNameOnlyMatch = User::factory()->create(['name' => 'Dragon Leader']);

    $matchingAlliance = Alliance::create([
        'name' => 'Dragon Watch',
        'slug' => 'dragon-watch',
        'leader_id' => $matchingLeader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $matchingAlliance->id,
        'user_id' => $matchingLeader->id,
        'role' => 'leader',
        'joined_at' => now(),
    ]);

    $leaderOnlyAlliance = Alliance::create([
        'name' => 'Plain Watch',
        'slug' => 'plain-watch',
        'leader_id' => $leaderNameOnlyMatch->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $leaderOnlyAlliance->id,
        'user_id' => $leaderNameOnlyMatch->id,
        'role' => 'leader',
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['alliance_search' => 'Dragon']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alliances.available.0.id', $matchingAlliance->id)
            ->missing('alliances.available.1')
        );
});

test('dashboard creates and exposes current weekly alliance goal', function () {
    $user = User::factory()->create();
    $leader = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Weekly Guild',
        'slug' => 'weekly-guild',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alliances.current.goal.name', 'Weekly stockpile')
            ->where('alliances.current.goal.resourceType', null)
            ->where('alliances.current.goal.stageCount', 6)
            ->where('alliances.current.goal.reachedStageCount', 0)
            ->where('alliances.current.activeGoalBonus.bonusPercent', 0)
        );

    $this->assertDatabaseHas('alliance_goals', [
        'alliance_id' => $alliance->id,
        'name' => 'Weekly stockpile',
        'resource_type' => null,
        'status' => 'active',
    ]);
});

test('dashboard syncs current generated alliance goal bonus percent from defaults', function () {
    $user = User::factory()->create();
    $leader = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Sync Guild',
        'slug' => 'sync-guild',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $weekStartsAt = now()->startOfWeek();
    $goal = AllianceGoal::create([
        'alliance_id' => $alliance->id,
        'name' => 'Weekly stockpile',
        'resource_type' => null,
        'target_amount' => 10_000_000,
        'current_amount' => 0,
        'production_bonus_percent' => 1,
        'stage_percentages' => [0.01, 1, 10, 30, 60, 100],
        'stage_donor_requirements' => [1, 2, 3, 4, 6, 8],
        'week_starts_at' => $weekStartsAt,
        'week_ends_at' => $weekStartsAt->copy()->addWeek()->subSecond(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alliances.current.goal.bonusPerStagePercent', AllianceGoalService::DEFAULT_PRODUCTION_BONUS_PERCENT)
            ->where('alliances.current.goal.potentialBonusPercent', AllianceGoalService::DEFAULT_PRODUCTION_BONUS_PERCENT * 6)
        );

    $this->assertDatabaseHas('alliance_goals', [
        'id' => $goal->id,
        'production_bonus_percent' => AllianceGoalService::DEFAULT_PRODUCTION_BONUS_PERCENT,
    ]);
});

test('members can contribute any resource to weekly alliance goals', function () {
    $user = User::factory()->create();
    $leader = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Donation Guild',
        'slug' => 'donation-guild',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 100,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $goal = AllianceGoal::query()
        ->where('alliance_id', $alliance->id)
        ->firstOrFail();

    $this->post(route('alliance-goals.contribute', $goal), [
        'resource_type' => 'wood',
        'amount' => 40,
    ])->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 60,
    ]);

    $this->assertDatabaseHas('alliance_goals', [
        'id' => $goal->id,
        'current_amount' => 40,
    ]);

    $this->assertDatabaseHas('alliance_goal_contributions', [
        'alliance_goal_id' => $goal->id,
        'user_id' => $user->id,
        'resource_type' => 'wood',
        'amount' => 40,
    ]);

    $this->assertDatabaseHas('alliance_user', [
        'alliance_id' => $alliance->id,
        'user_id' => $user->id,
        'total_contributed' => 40,
    ]);
});

test('dashboard exposes current alliance contribution history', function () {
    $user = User::factory()->create(['name' => 'Current Player']);
    $otherUser = User::factory()->create(['name' => 'Donor Player']);
    $leader = User::factory()->create();

    $alliance = Alliance::factory()
        ->for($leader, 'leader')
        ->create([
            'name' => 'History Guild',
            'slug' => 'history-guild',
        ]);

    AllianceMembership::factory()
        ->for($alliance)
        ->for($user)
        ->create();

    AllianceMembership::factory()
        ->for($alliance)
        ->for($otherUser)
        ->contributed(500)
        ->create();

    $goal = AllianceGoal::factory()
        ->for($alliance)
        ->forResource('wood')
        ->create([
            'name' => 'Wood Reserve',
        ]);

    AllianceGoalContribution::factory()
        ->for($goal, 'goal')
        ->for($user)
        ->forResource('wood')
        ->amount(100)
        ->create([
            'created_at' => now()->subHour(),
        ]);

    AllianceGoalContribution::factory()
        ->for($goal, 'goal')
        ->for($otherUser)
        ->forResource('food')
        ->amount(250)
        ->create([
            'created_at' => now(),
        ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alliances.current.contributions.0.userName', 'Donor Player')
            ->where('alliances.current.contributions.0.goalName', 'Wood Reserve')
            ->where('alliances.current.contributions.0.resourceType', 'food')
            ->where('alliances.current.contributions.0.amount', 250)
            ->where('alliances.current.contributions.0.amountLabel', '250')
            ->where('alliances.current.contributions.1.userName', 'Current Player')
            ->where('alliances.current.contributions.1.resourceType', 'wood')
            ->where('alliances.current.contributions.1.amount', 100)
        );
});

test('members cannot contribute more than twenty percent of an alliance goal', function () {
    $user = User::factory()->create();
    $leader = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Cap Guild',
        'slug' => 'cap-guild',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 500,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $weekStartsAt = now()->startOfWeek();
    $goal = AllianceGoal::create([
        'alliance_id' => $alliance->id,
        'name' => 'Small Stockpile',
        'resource_type' => null,
        'target_amount' => 1_000,
        'current_amount' => 150,
        'production_bonus_percent' => 5,
        'stage_percentages' => [50, 100],
        'stage_donor_requirements' => [1, 2],
        'week_starts_at' => $weekStartsAt,
        'week_ends_at' => $weekStartsAt->copy()->addWeek()->subSecond(),
        'status' => 'active',
    ]);

    AllianceGoalContribution::create([
        'alliance_goal_id' => $goal->id,
        'user_id' => $user->id,
        'resource_type' => 'wood',
        'amount' => 150,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('alliance-goals.contribute', $goal), [
            'resource_type' => 'wood',
            'amount' => 60,
        ])
        ->assertSessionHasErrors('alliance_goal');

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 500,
    ]);

    $this->assertDatabaseHas('alliance_goals', [
        'id' => $goal->id,
        'current_amount' => 150,
    ]);
});

test('previous week alliance goal stages add production bonus this week', function () {
    $user = User::factory()->create();
    $leader = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Bonus Guild',
        'slug' => 'bonus-guild',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $previousWeekStartsAt = now()->startOfWeek()->subWeek();

    $previousGoal = AllianceGoal::create([
        'alliance_id' => $alliance->id,
        'name' => 'Previous Stockpile',
        'resource_type' => null,
        'target_amount' => 1_000,
        'current_amount' => 300,
        'production_bonus_percent' => 5,
        'stage_percentages' => [10, 30, 60, 100],
        'stage_donor_requirements' => [1, 2, 3, 4],
        'week_starts_at' => $previousWeekStartsAt,
        'week_ends_at' => $previousWeekStartsAt->copy()->addWeek()->subSecond(),
        'status' => 'expired',
    ]);

    AllianceGoalContribution::create([
        'alliance_goal_id' => $previousGoal->id,
        'user_id' => $user->id,
        'resource_type' => 'wood',
        'amount' => 150,
        'created_at' => $previousWeekStartsAt,
    ]);

    AllianceGoalContribution::create([
        'alliance_goal_id' => $previousGoal->id,
        'user_id' => $leader->id,
        'resource_type' => 'food',
        'amount' => 150,
        'created_at' => $previousWeekStartsAt,
    ]);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 100,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alliances.current.activeGoalBonus.bonusPercent', 10)
            ->where('alliances.current.activeGoalBonus.stageCount', 2)
            ->where('resourceRates.gold', 111)
            ->where('buildings.0.production', '+111 gold/hour')
        );
});

test('alliance goal stages require enough unique donors for production bonus', function () {
    $user = User::factory()->create();
    $leader = User::factory()->create();
    $secondDonor = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Donor Guild',
        'slug' => 'donor-guild',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $previousWeekStartsAt = now()->startOfWeek()->subWeek();

    $previousGoal = AllianceGoal::create([
        'alliance_id' => $alliance->id,
        'name' => 'Donor Stockpile',
        'resource_type' => null,
        'target_amount' => 1_000,
        'current_amount' => 1_000,
        'production_bonus_percent' => 5,
        'stage_percentages' => [10, 100],
        'stage_donor_requirements' => [1, 8],
        'week_starts_at' => $previousWeekStartsAt,
        'week_ends_at' => $previousWeekStartsAt->copy()->addWeek()->subSecond(),
        'status' => 'expired',
    ]);

    AllianceGoalContribution::create([
        'alliance_goal_id' => $previousGoal->id,
        'user_id' => $user->id,
        'resource_type' => 'wood',
        'amount' => 500,
        'created_at' => $previousWeekStartsAt,
    ]);

    AllianceGoalContribution::create([
        'alliance_goal_id' => $previousGoal->id,
        'user_id' => $secondDonor->id,
        'resource_type' => 'food',
        'amount' => 500,
        'created_at' => $previousWeekStartsAt,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alliances.current.activeGoalBonus.bonusPercent', 5)
            ->where('alliances.current.activeGoalBonus.stageCount', 1)
        );
});

test('alliance goal stays active at target amount until donor segments are reached', function () {
    $leader = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Target Guild',
        'slug' => 'target-guild',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    $weekStartsAt = now()->startOfWeek();
    $goal = AllianceGoal::create([
        'alliance_id' => $alliance->id,
        'name' => 'Target Stockpile',
        'resource_type' => null,
        'target_amount' => 1_000,
        'current_amount' => 1_000,
        'production_bonus_percent' => 5,
        'stage_percentages' => [100],
        'stage_donor_requirements' => [8],
        'week_starts_at' => $weekStartsAt,
        'week_ends_at' => $weekStartsAt->copy()->addWeek()->subSecond(),
        'status' => 'active',
    ]);

    foreach (User::factory()->count(5)->create() as $donor) {
        AllianceGoalContribution::create([
            'alliance_goal_id' => $goal->id,
            'user_id' => $donor->id,
            'resource_type' => 'wood',
            'amount' => 200,
            'created_at' => now(),
        ]);
    }

    app(AllianceGoalService::class)->refreshGoalStatus($goal);

    $this->assertDatabaseHas('alliance_goals', [
        'id' => $goal->id,
        'status' => 'active',
        'completed_at' => null,
    ]);
});

test('alliance goal completes when target amount and donor segments are reached', function () {
    $leader = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Complete Guild',
        'slug' => 'complete-guild',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    $weekStartsAt = now()->startOfWeek();
    $goal = AllianceGoal::create([
        'alliance_id' => $alliance->id,
        'name' => 'Complete Stockpile',
        'resource_type' => null,
        'target_amount' => 1_000,
        'current_amount' => 1_000,
        'production_bonus_percent' => 5,
        'stage_percentages' => [100],
        'stage_donor_requirements' => [8],
        'week_starts_at' => $weekStartsAt,
        'week_ends_at' => $weekStartsAt->copy()->addWeek()->subSecond(),
        'status' => 'active',
    ]);

    foreach (User::factory()->count(8)->create() as $donor) {
        AllianceGoalContribution::create([
            'alliance_goal_id' => $goal->id,
            'user_id' => $donor->id,
            'resource_type' => 'wood',
            'amount' => 125,
            'created_at' => now(),
        ]);
    }

    app(AllianceGoalService::class)->refreshGoalStatus($goal);

    $this->assertDatabaseHas('alliance_goals', [
        'id' => $goal->id,
        'status' => 'completed',
    ]);

    expect($goal->fresh()?->completed_at)->not->toBeNull();
});

test('dashboard reads weather code from the database', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    WeatherSnapshot::create([
        'latitude' => Weather::LATITUDE,
        'longitude' => Weather::LONGITUDE,
        'weather_code' => 61,
        'api_time' => now(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('weather.latitude', Weather::LATITUDE)
            ->where('weather.longitude', Weather::LONGITUDE)
            ->where('weather.weatherCode', 61)
            ->where('weather.conditions.clear', false)
            ->where('weather.conditions.raining', true)
            ->where('weather.conditions.foggy', false)
            ->where('weather.conditions.thunderstorm', false)
            ->where('weather.conditions.snowing', false)
        );
});

test('dashboard exposes simplified weather conditions for later immersive mode use', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    WeatherSnapshot::create([
        'latitude' => Weather::LATITUDE,
        'longitude' => Weather::LONGITUDE,
        'weather_code' => 71,
        'api_time' => now(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('weather.weatherCode', 71)
            ->where('weather.conditions.clear', false)
            ->where('weather.conditions.raining', false)
            ->where('weather.conditions.foggy', false)
            ->where('weather.conditions.thunderstorm', false)
            ->where('weather.conditions.snowing', true)
        );
});

test('weather update command stores open meteo weather code', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response([
            'timezone' => 'Europe/Vilnius',
            'current' => [
                'time' => '2026-05-28T12:03',
                'weather_code' => 71,
            ],
        ]),
    ]);

    $this->artisan('weather:update')
        ->assertSuccessful();

    $this->assertDatabaseHas('weather_snapshots', [
        'latitude' => Weather::LATITUDE,
        'longitude' => Weather::LONGITUDE,
        'weather_code' => 71,
        'api_time' => '2026-05-28 09:03:00',
    ]);
});

test('users can save browser supplied coordinates and weather', function () {
    Http::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from(route('immersive'))->post(route('dashboard.weather-location'), [
        'latitude' => 55.1234,
        'longitude' => 24.9876,
        'weather_code' => 45,
        'api_time' => '2026-05-29T06:18Z',
    ])->assertRedirect(route('immersive'));

    $this->assertDatabaseHas('weather_snapshots', [
        'user_id' => $user->id,
        'latitude' => 55.1234,
        'longitude' => 24.9876,
        'weather_code' => 45,
        'api_time' => '2026-05-29 06:18:00',
    ]);

    Http::assertNothingSent();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('weather.latitude', 55.1234)
            ->where('weather.longitude', 24.9876)
            ->where('weather.isUsingGeolocation', true)
            ->whereNot('weather.locationUpdatedAt', null)
            ->where('weather.weatherCode', 45)
            ->where('weather.conditions.foggy', true)
        );
});

test('users can switch weather back to default location', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    WeatherSnapshot::create([
        'user_id' => $user->id,
        'latitude' => 55.1234,
        'longitude' => 24.9876,
        'weather_code' => 45,
        'api_time' => now(),
    ]);

    WeatherSnapshot::create([
        'latitude' => Weather::LATITUDE,
        'longitude' => Weather::LONGITUDE,
        'weather_code' => 0,
        'api_time' => now(),
    ]);

    $this->from(route('immersive'))->post(route('dashboard.weather-location.default'))
        ->assertRedirect(route('immersive'));

    $this->assertDatabaseMissing('weather_snapshots', [
        'user_id' => $user->id,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('weather.latitude', Weather::LATITUDE)
            ->where('weather.longitude', Weather::LONGITUDE)
            ->where('weather.isUsingGeolocation', false)
            ->where('weather.locationUpdatedAt', null)
            ->where('weather.weatherCode', 0)
        );
});

test('weather update command only refreshes the default server weather location', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response([
            'timezone' => 'Europe/Vilnius',
            'current' => [
                'time' => '2026-05-29T09:18',
                'weather_code' => 95,
            ],
        ]),
    ]);

    $user = User::factory()->create();
    WeatherSnapshot::create([
        'user_id' => $user->id,
        'latitude' => 55.1234,
        'longitude' => 24.9876,
        'weather_code' => 45,
        'api_time' => now(),
    ]);

    $this->artisan('weather:update')
        ->assertSuccessful();

    $this->assertDatabaseHas('weather_snapshots', [
        'latitude' => Weather::LATITUDE,
        'longitude' => Weather::LONGITUDE,
        'weather_code' => 95,
    ]);

    $this->assertDatabaseHas('weather_snapshots', [
        'user_id' => $user->id,
        'latitude' => 55.1234,
        'longitude' => 24.9876,
        'weather_code' => 45,
    ]);
});

test('daily collect adds base rewards and six hours of building production', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 2,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    $sawmill = BuildingType::create([
        'name' => 'Sawmill',
        'slug' => 'sawmill',
        'produces_resource' => 'wood',
        'base_production_per_hour' => 1,
        'production_multiplier' => 1,
        'base_costs' => ['gold' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $sawmill->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    $this->post(route('dashboard.collect'))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'gold' => 12,
        'wood' => 36,
        'stone' => 0,
        'food' => 20,
        'lifetime_gold' => 12,
        'lifetime_wood' => 36,
        'lifetime_stone' => 0,
        'lifetime_food' => 20,
        'manual_collects' => 1,
    ]);

    $this->assertDatabaseHas('resource_collections', [
        'user_id' => $user->id,
        'gold' => 12,
        'wood' => 36,
        'stone' => 0,
        'food' => 20,
        'source' => 'manual',
    ]);
});

test('daily collect can only be used once per day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $farm = BuildingType::create([
        'name' => 'Farm',
        'slug' => 'farm',
        'produces_resource' => 'food',
        'base_production_per_hour' => 1,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $farm->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    $this->post(route('dashboard.collect'))
        ->assertRedirect(route('dashboard'));

    $this->post(route('dashboard.collect'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('collect');

    $resources = UserResource::where('user_id', $user->id)->firstOrFail();

    expect($resources->wood)->toBe(30)
        ->and($resources->food)->toBe(26)
        ->and($resources->manual_collects)->toBe(1);

    expect(ResourceCollection::where('user_id', $user->id)->count())->toBe(1);
});

test('daily collect returns to immersive mode when submitted from immersive mode', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from(route('immersive'))
        ->post(route('dashboard.collect'))
        ->assertRedirect(route('immersive'));

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 30,
        'food' => 20,
        'manual_collects' => 1,
    ]);
});

test('minigame completion awards resources from current production and tracks completions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $lumbercamp = BuildingType::create([
        'name' => 'Lumbercamp',
        'slug' => 'lumbercamp',
        'produces_resource' => 'wood',
        'base_production_per_hour' => 250,
        'production_multiplier' => 1,
        'base_costs' => ['gold' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $lumbercamp->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    $achievement = Achievement::create([
        'name' => 'Wood Minigame 1',
        'slug' => 'wood-minigame-completions-1',
        'description' => 'Complete the wood minigame once.',
        'type' => 'minigame_completions',
        'resource_type' => 'wood',
        'target_value' => 1,
        'production_bonus_percent' => 0,
    ]);

    $this->post(route('dashboard.minigames.complete', ['resource' => 'wood']))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 6,
        'lifetime_wood' => 6,
    ]);

    $this->assertDatabaseHas('minigames', [
        'user_id' => $user->id,
        'resource' => 'wood',
        'completions' => 1,
        'resources_gained' => 6,
    ]);

    $this->assertDatabaseHas('resource_collections', [
        'user_id' => $user->id,
        'wood' => 6,
        'source' => 'minigame_wood',
    ]);

    $this->assertDatabaseHas('user_achievements', [
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 1,
    ]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('minigames.0.resource', 'wood')
            ->where('minigames.0.currentProduction', 250)
            ->where('minigames.0.reward', 6)
            ->where('minigames.0.completions', 1)
            ->where('minigames.0.resourcesGained', 6)
            ->where('minigames.0.stamina.current', MinigameStamina::MAX_COMPLETIONS_PER_HOUR - 1)
            ->where('minigames.0.stamina.used', 1)
            ->where('minigames.0.stamina.isAvailable', true)
            ->where('minigames.3.resource', 'gold')
            ->where('minigames.3.stamina.current', MinigameStamina::MAX_COMPLETIONS_PER_HOUR - 1)
            ->where('minigames.3.stamina.used', 1)
            ->where('minigames.3.stamina.isAvailable', true));
});

test('minigame stamina is shared across minigames from recent resource collection history', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $resources = UserResource::factory()
        ->for($user)
        ->withResources(gold: 0)
        ->create();

    foreach (range(1, MinigameStamina::MAX_COMPLETIONS_PER_HOUR) as $index) {
        ResourceCollection::factory()
            ->for($user)
            ->create([
                'wood' => 1,
                'source' => MinigameStamina::sourceFor('wood'),
                'collected_at' => now()->subMinutes(30),
            ]);
    }

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('minigames.0.resource', 'wood')
            ->where('minigames.0.stamina.current', 0)
            ->where('minigames.0.stamina.used', MinigameStamina::MAX_COMPLETIONS_PER_HOUR)
            ->where('minigames.0.stamina.isAvailable', false)
            ->where('minigames.3.resource', 'gold')
            ->where('minigames.3.stamina.current', 0)
            ->where('minigames.3.stamina.used', MinigameStamina::MAX_COMPLETIONS_PER_HOUR)
            ->where('minigames.3.stamina.isAvailable', false)
        );

    $this->post(route('dashboard.minigames.complete', ['resource' => 'gold']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('minigame');

    $this->assertDatabaseHas('user_resources', [
        'id' => $resources->id,
        'gold' => 0,
    ]);

    $this->assertDatabaseHas('minigames', [
        'user_id' => $user->id,
        'resource' => 'gold',
        'completions' => 0,
        'resources_gained' => 0,
    ]);
});

test('minigame completion returns to immersive mode when submitted from immersive mode', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from(route('immersive'))
        ->post(route('dashboard.minigames.complete', ['resource' => 'wood']))
        ->assertRedirect(route('immersive'));

    $this->assertDatabaseHas('minigames', [
        'user_id' => $user->id,
        'resource' => 'wood',
        'completions' => 1,
    ]);
});

test('minigame completions are rate limited per user and resource', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    RateLimiter::clear('minigame:'.$user->id.':gold:minute');
    RateLimiter::clear('minigame:'.$user->id.':gold:hour');

    for ($attempt = 0; $attempt < 20; $attempt += 1) {
        $this->post(route('dashboard.minigames.complete', ['resource' => 'gold']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();
    }

    $this->post(route('dashboard.minigames.complete', ['resource' => 'gold']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('minigame');

    $this->assertDatabaseHas('minigames', [
        'user_id' => $user->id,
        'resource' => 'gold',
        'completions' => 20,
    ]);
});

test('daily collect gives one hundred of each resource after first prestige', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'prestiges' => 1,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.collect'))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'gold' => 100,
        'wood' => 100,
        'stone' => 100,
        'food' => 100,
        'lifetime_gold' => 100,
        'lifetime_wood' => 100,
        'lifetime_stone' => 100,
        'lifetime_food' => 100,
        'manual_collects' => 1,
    ]);

    $this->assertDatabaseHas('resource_collections', [
        'user_id' => $user->id,
        'gold' => 100,
        'wood' => 100,
        'stone' => 100,
        'food' => 100,
        'source' => 'manual',
    ]);
});

test('level zero buildings do not produce resources', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 2,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    $this->post(route('dashboard.collect'))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_buildings', [
        'user_id' => $user->id,
        'level' => 0,
        'built_at' => null,
    ]);

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 30,
        'stone' => 0,
        'food' => 20,
    ]);
});

test('dashboard applies passive production for full elapsed hours', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 1,
        'built_at' => now()->subHours(4),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now()->subHours(3)->subMinutes(30),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offlineProgress.elapsedHours', 3)
            ->where('offlineProgress.resources.gold', 15)
            ->where('offlineProgress.resources.wood', 0)
            ->where('offlineProgress.resources.stone', 0)
            ->where('offlineProgress.resources.food', 0)
            ->where('offlineProgress.total', 15));

    $resources = UserResource::where('user_id', $user->id)->firstOrFail();

    expect($resources->gold)->toBe(15)
        ->and($resources->lifetime_gold)->toBe(15);

    $this->assertDatabaseHas('resource_collections', [
        'user_id' => $user->id,
        'gold' => 15,
        'source' => 'passive',
    ]);
});

test('users can build a level zero building by paying base cost', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100, 'stone' => 50],
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 0,
        'built_at' => null,
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 120,
        'stone' => 60,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_buildings', [
        'id' => $building->id,
        'level' => 1,
    ]);

    expect($building->fresh()->built_at)->not->toBeNull();

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 20,
        'stone' => 10,
    ]);
});

test('users can upgrade a built building by paying scaled cost', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
        'upgrade_cost_multiplier' => 1.25,
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 2,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 200,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_buildings', [
        'id' => $building->id,
        'level' => 3,
    ]);

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 43,
    ]);
});

test('building upgrade returns to immersive mode when submitted from immersive mode', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 0,
        'built_at' => null,
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 120,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->from(route('immersive'))
        ->post(route('dashboard.buildings.upgrade', $building))
        ->assertRedirect(route('immersive'));

    $this->assertDatabaseHas('user_buildings', [
        'id' => $building->id,
        'level' => 1,
    ]);
});

test('users cannot upgrade without enough resources', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 0,
        'built_at' => null,
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 99,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('upgrade');

    $this->assertDatabaseHas('user_buildings', [
        'id' => $building->id,
        'level' => 0,
        'built_at' => null,
    ]);

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 99,
    ]);
});

test('max level buildings are shown as maxed and cannot be upgraded', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
        'max_level' => 3,
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 3,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 500,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('buildings.0.levelLabel', 'Level 3')
            ->where('buildings.0.isMaxLevel', true)
            ->where('buildings.0.canUpgrade', false)
        );
});

test('building display numbers use thousands separators', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $road = BuildingType::create([
        'name' => 'Road',
        'slug' => 'road',
        'produces_resource' => null,
        'base_production_per_hour' => 0,
        'production_multiplier' => null,
        'effect_type' => 'road_length',
        'base_costs' => ['wood' => 1000, 'stone' => 1500000],
        'upgrade_cost_multiplier' => 1,
    ]);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 1000,
        'production_multiplier' => 2,
        'base_costs' => ['wood' => 2000000],
        'upgrade_cost_multiplier' => 1,
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 1000,
        'built_at' => now(),
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 2,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 2_000_000,
        'stone' => 2_000_000,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('buildings.0.levelLabel', '1,000 km')
            ->where('buildings.0.production', '1,000 km built')
            ->where('buildings.0.upgradeCost', '1,000 wood, 1,500,000 stone')
            ->where('buildings.1.levelLabel', 'Level 2')
            ->where('buildings.1.production', '+2,000 gold/hour')
            ->where('buildings.1.upgradeCost', '2,000,000 wood')
        );
});

test('achievements are shown and unlock production bonuses', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 10,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'lifetime_gold' => 100,
        'last_produced_at' => now(),
    ]);

    Achievement::create([
        'name' => 'Gold Starter',
        'slug' => 'gold-starter',
        'description' => 'Earn 100 gold.',
        'type' => 'resource_lifetime',
        'resource_type' => 'gold',
        'target_value' => 100,
        'production_bonus_percent' => 50,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('achievements.0.name', 'Gold Starter')
            ->where('achievements.0.isUnlocked', true)
            ->where('achievements.0.progressLabel', '100 / 100')
            ->where('achievements.0.rewardLabel', '+50% all buildings base production')
            ->where('achievementBonuses.0.label', 'All buildings')
            ->where('achievementBonuses.0.bonusPercent', 50)
            ->where('achievementBonuses.0.bonusLabel', '+50%')
            ->where('achievementUnlocks.0.name', 'Gold Starter')
            ->where('achievementUnlocks.0.rewardLabel', '+50% all buildings base production')
            ->where('resourceRates.gold', 15)
            ->where('buildings.0.production', '+15 gold/hour')
        );
});

test('achievement production bonuses can target a specific building type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 10,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    $farm = BuildingType::create([
        'name' => 'Farm',
        'slug' => 'farm',
        'produces_resource' => 'food',
        'base_production_per_hour' => 10,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $farm->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $achievement = Achievement::create([
        'name' => 'Better Mines',
        'slug' => 'better-mines',
        'description' => 'Improve mine output.',
        'type' => 'building_level',
        'building_type_id' => $mine->id,
        'target_value' => 1,
        'production_bonus_percent' => 100,
        'bonus_building_type_id' => $mine->id,
    ]);

    UserAchievement::create([
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 1,
        'unlocked_at' => now(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('resourceRates.gold', 20)
            ->where('resourceRates.food', 10)
            ->where('buildings.0.production', '+20 gold/hour')
            ->where('buildings.1.production', '+10 food/hour')
            ->where('achievements.0.rewardLabel', '+100% Mine base production')
            ->where('achievementBonuses.0.label', 'Mine')
            ->where('achievementBonuses.0.bonusPercent', 100)
            ->where('achievementBonuses.0.bonusLabel', '+100%')
            ->where('achievementUnlocks.0.name', 'Better Mines')
            ->where('achievementUnlocks.0.rewardLabel', '+100% Mine base production')
        );
});

test('users can mark achievement unlock popups as seen', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $achievement = Achievement::create([
        'name' => 'Dedicated Collector',
        'slug' => 'dedicated-collector',
        'description' => 'Keep collecting.',
        'type' => 'manual_collects',
        'target_value' => 10,
        'production_bonus_percent' => 5,
    ]);

    $userAchievement = UserAchievement::create([
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 10,
        'unlocked_at' => now(),
        'notification_seen_at' => null,
    ]);

    $this->post(route('dashboard.achievements.unlocks.seen'), [
        'ids' => [$userAchievement->id],
    ])->assertRedirect(route('dashboard'));

    expect($userAchievement->fresh()->notification_seen_at)->not->toBeNull();
});

test('achievement unlock popups return to immersive mode when marked seen from immersive mode', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $achievement = Achievement::create([
        'name' => 'Immersive Collector',
        'slug' => 'immersive-collector',
        'description' => 'Keep collecting in immersive mode.',
        'type' => 'manual_collects',
        'target_value' => 10,
        'production_bonus_percent' => 5,
    ]);

    $userAchievement = UserAchievement::create([
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 10,
        'unlocked_at' => now(),
        'notification_seen_at' => null,
    ]);

    $this->from(route('immersive'))
        ->post(route('dashboard.achievements.unlocks.seen'), [
            'ids' => [$userAchievement->id],
        ])
        ->assertRedirect(route('immersive'));

    expect($userAchievement->fresh()->notification_seen_at)->not->toBeNull();
});

test('users can build multiple kilometers of road at once', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $road = BuildingType::create([
        'name' => 'Road',
        'slug' => 'road',
        'produces_resource' => null,
        'base_production_per_hour' => 0,
        'production_multiplier' => null,
        'effect_type' => 'road_length',
        'base_costs' => ['wood' => 10],
        'max_level' => 10,
        'upgrade_cost_multiplier' => 2,
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 0,
        'built_at' => null,
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 100,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building), [
        'amount' => 3,
    ])->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_buildings', [
        'id' => $building->id,
        'level' => 3,
    ]);

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 30,
    ]);
});

test('users can build up to one million kilometers of road at once', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $road = BuildingType::create([
        'name' => 'Road',
        'slug' => 'road',
        'produces_resource' => null,
        'base_production_per_hour' => 0,
        'production_multiplier' => null,
        'effect_type' => 'road_length',
        'base_costs' => ['wood' => 1],
        'upgrade_cost_multiplier' => 1,
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 0,
        'built_at' => null,
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 1_000_000,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building), [
        'amount' => 1_000_000,
    ])->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_buildings', [
        'id' => $building->id,
        'level' => 1_000_000,
    ]);

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 0,
    ]);
});

test('roads are displayed as kilometers instead of production per hour', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $road = BuildingType::create([
        'name' => 'Road',
        'slug' => 'road',
        'produces_resource' => null,
        'base_production_per_hour' => 0,
        'production_multiplier' => null,
        'effect_type' => 'road_length',
        'base_costs' => ['wood' => 10],
        'max_level' => 10,
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 4,
        'built_at' => now(),
    ]);

    $leader = User::factory()->create();
    UserBuilding::create([
        'user_id' => $leader->id,
        'building_type_id' => $road->id,
        'level' => 6,
        'built_at' => now(),
    ]);
    UserResource::create([
        'user_id' => $leader->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'prestiges' => 2,
        'last_produced_at' => now(),
    ]);

    $tiedUser = User::factory()->create();
    UserBuilding::create([
        'user_id' => $tiedUser->id,
        'building_type_id' => $road->id,
        'level' => 4,
        'built_at' => now(),
    ]);
    UserResource::create([
        'user_id' => $tiedUser->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'prestiges' => 0,
        'last_produced_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 100,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('buildings.0.levelLabel', '4 km')
            ->where('buildings.0.production', '4 km built')
            ->where('buildings.0.isRoad', true)
            ->where('roadStats.length', 4)
            ->where('prestigeStats.count', 0)
            ->where('prestigeStats.rank', 2)
            ->where('prestigeStats.canPrestige', false)
            ->where('prestigeStats.requirement', 10)
        );
});

test('dashboard includes prestige manual collect and minigame leaderboards', function () {
    $user = User::factory()->create(['name' => 'Current Player']);
    $this->actingAs($user);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'manual_collects' => 3,
        'prestiges' => 2,
        'last_produced_at' => now(),
    ]);

    Minigame::create([
        'user_id' => $user->id,
        'resource' => 'wood',
        'completions' => 4,
        'resources_gained' => 12,
    ]);

    $leader = User::factory()->create(['name' => 'Top Player']);
    UserResource::create([
        'user_id' => $leader->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'manual_collects' => 8,
        'prestiges' => 5,
        'last_produced_at' => now(),
    ]);

    Minigame::create([
        'user_id' => $leader->id,
        'resource' => 'wood',
        'completions' => 9,
        'resources_gained' => 40,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('leaderboards.defaultKey', 'prestige')
            ->where('leaderboards.boards.0.key', 'prestige')
            ->where('leaderboards.boards.0.currentRank', 2)
            ->where('leaderboards.boards.0.entries.0.userName', 'Top Player')
            ->where('leaderboards.boards.0.entries.1.userName', 'Current Player')
            ->where('leaderboards.boards.1.key', 'manual_collects')
            ->where('leaderboards.boards.1.currentRank', 2)
            ->where('leaderboards.boards.2.key', 'wood_minigame')
            ->where('leaderboards.boards.2.currentRank', 2)
            ->where('leaderboards.boards.2.entries.0.value', 9)
        );
});

test('users can prestige after building roads to the road max level', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $road = BuildingType::create([
        'name' => 'Road',
        'slug' => 'road',
        'produces_resource' => null,
        'base_production_per_hour' => 0,
        'production_multiplier' => null,
        'effect_type' => 'road_length',
        'base_costs' => ['wood' => 10],
        'max_level' => 8,
    ]);

    $roadBuilding = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 8,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 100,
        'wood' => 200,
        'stone' => 300,
        'food' => 400,
        'lifetime_gold' => 100,
        'manual_collects' => 7,
        'last_produced_at' => now(),
        'last_collected_at' => now(),
    ]);

    $keptAchievement = Achievement::create([
        'name' => 'Road Master',
        'slug' => 'road-master',
        'description' => 'A previous unlock.',
        'type' => 'road_length',
        'target_value' => 100,
        'production_bonus_percent' => 5,
    ]);

    $prestigeAchievement = Achievement::create([
        'name' => '1 Prestige',
        'slug' => 'prestiges-1',
        'description' => 'Prestige once.',
        'type' => 'prestiges',
        'target_value' => 1,
        'production_bonus_percent' => 5,
    ]);

    UserAchievement::create([
        'user_id' => $user->id,
        'achievement_id' => $keptAchievement->id,
        'progress' => 100,
        'unlocked_at' => now(),
        'notification_seen_at' => now(),
    ]);

    $this->post(route('dashboard.prestige'))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'lifetime_gold' => 100,
        'manual_collects' => 7,
        'prestiges' => 1,
        'last_collected_at' => null,
    ]);

    $this->assertDatabaseHas('user_buildings', [
        'id' => $roadBuilding->id,
        'level' => 0,
        'built_at' => null,
    ]);

    $this->assertDatabaseHas('user_achievements', [
        'user_id' => $user->id,
        'achievement_id' => $keptAchievement->id,
    ]);

    $this->assertDatabaseHas('user_achievements', [
        'user_id' => $user->id,
        'achievement_id' => $prestigeAchievement->id,
        'progress' => 1,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('achievements.1.rewardLabel', '+5% all buildings base production, daily collect base becomes 100 of each resource')
        );
});

test('prestige returns to immersive mode when submitted from immersive mode', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $road = BuildingType::create([
        'name' => 'Road',
        'slug' => 'road',
        'produces_resource' => null,
        'base_production_per_hour' => 0,
        'production_multiplier' => null,
        'effect_type' => 'road_length',
        'base_costs' => ['wood' => 10],
        'max_level' => 8,
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 8,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'wood' => 200,
        'last_produced_at' => now(),
    ]);

    $this->from(route('immersive'))
        ->post(route('dashboard.prestige'))
        ->assertRedirect(route('immersive'));

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'prestiges' => 1,
    ]);
});

test('users cannot prestige before reaching sixty million kilometers of road', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $road = BuildingType::create([
        'name' => 'Road',
        'slug' => 'road',
        'produces_resource' => null,
        'base_production_per_hour' => 0,
        'production_multiplier' => null,
        'effect_type' => 'road_length',
        'base_costs' => ['wood' => 10],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 59_999_999,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 100,
        'wood' => 100,
        'stone' => 100,
        'food' => 100,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.prestige'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('prestige');

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'gold' => 100,
        'prestiges' => 0,
    ]);
});
