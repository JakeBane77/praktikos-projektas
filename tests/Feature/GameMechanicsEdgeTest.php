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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('users can build exactly ten million kilometers of road at once', function () {
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
        'max_level' => 10_000_000,
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 0,
        'built_at' => null,
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'wood' => 10_000_000,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building), [
        'amount' => 10_000_000,
    ])->assertRedirect(route('dashboard'));

    expect($building->fresh()->level)->toBe(10_000_000);

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 0,
    ]);
});

test('building upgrade costs use the configured multiplier for multiple resources', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $quarry = BuildingType::create([
        'name' => 'Quarry',
        'slug' => 'quarry',
        'produces_resource' => 'stone',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 10, 'food' => 5],
        'upgrade_cost_multiplier' => 1.5,
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $quarry->id,
        'level' => 3,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'wood' => 100,
        'food' => 100,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building))
        ->assertRedirect(route('dashboard'));

    expect($building->fresh()->level)->toBe(4);

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 66,
        'food' => 83,
    ]);
});

test('road batch upgrade costs use geometric cost scaling when multiplier is not one', function () {
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
        'upgrade_cost_multiplier' => 2,
        'max_level' => 10,
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 2,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'wood' => 300,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building), [
        'amount' => 3,
    ])->assertRedirect(route('dashboard'));

    expect($building->fresh()->level)->toBe(5);

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 20,
    ]);
});

test('upgrade cost labels round high level exponential costs upward', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 1,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 3],
        'upgrade_cost_multiplier' => 1.2,
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 5,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'wood' => 100,
        'last_produced_at' => now(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('buildings.0.upgradeCost', '8 wood')
        );
});

test('partial passive production hours do not create resources or advance production time', function () {
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

    $lastProducedAt = now()->subMinutes(59);
    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'last_produced_at' => $lastProducedAt,
    ]);

    $this->get(route('dashboard'))->assertOk();

    $resources = UserResource::where('user_id', $user->id)->firstOrFail();

    expect($resources->gold)->toBe(0)
        ->and($resources->last_produced_at?->format('Y-m-d H:i:s'))->toBe($lastProducedAt->format('Y-m-d H:i:s'))
        ->and(ResourceCollection::where('user_id', $user->id)->exists())->toBeFalse();
});

test('passive production advances only by full elapsed hours', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $farm = BuildingType::create([
        'name' => 'Farm',
        'slug' => 'farm',
        'produces_resource' => 'food',
        'base_production_per_hour' => 7,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $farm->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    $lastProducedAt = now()->subHours(2)->subMinutes(45);
    UserResource::create([
        'user_id' => $user->id,
        'food' => 0,
        'last_produced_at' => $lastProducedAt,
    ]);

    $this->get(route('dashboard'))->assertOk();

    $resources = UserResource::where('user_id', $user->id)->firstOrFail();

    expect($resources->food)->toBe(14)
        ->and($resources->last_produced_at?->format('Y-m-d H:i:s'))->toBe($lastProducedAt->copy()->addHours(2)->format('Y-m-d H:i:s'));
});

test('passive production applies unlocked achievement production bonuses', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $lumbercamp = BuildingType::create([
        'name' => 'Lumbercamp',
        'slug' => 'lumbercamp',
        'produces_resource' => 'wood',
        'base_production_per_hour' => 10,
        'production_multiplier' => 1,
        'base_costs' => ['food' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $lumbercamp->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    $achievement = Achievement::create([
        'name' => 'Better Production',
        'slug' => 'better-production',
        'description' => 'Improve all buildings.',
        'type' => 'manual_collects',
        'target_value' => 1,
        'production_bonus_percent' => 100,
    ]);

    UserAchievement::create([
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 1,
        'unlocked_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'wood' => 0,
        'last_produced_at' => now()->subHours(2),
    ]);

    $this->get(route('dashboard'))->assertOk();

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 40,
        'lifetime_wood' => 40,
    ]);
});

test('passive production applies previous week alliance goal production bonuses', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $alliance = Alliance::factory()
        ->for($user, 'leader')
        ->create();

    AllianceMembership::factory()
        ->leader()
        ->for($alliance)
        ->for($user)
        ->create();

    $lumbercamp = BuildingType::create([
        'name' => 'Lumbercamp',
        'slug' => 'lumbercamp',
        'produces_resource' => 'wood',
        'base_production_per_hour' => 10,
        'production_multiplier' => 1,
        'base_costs' => ['food' => 100],
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $lumbercamp->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    $previousGoal = AllianceGoal::factory()
        ->for($alliance)
        ->previousWeek()
        ->withProgress(100)
        ->withStages([100], [1])
        ->create([
            'target_amount' => 100,
            'production_bonus_percent' => 50,
        ]);

    AllianceGoalContribution::factory()
        ->for($previousGoal, 'goal')
        ->for($user)
        ->forResource('wood')
        ->amount(100)
        ->create();

    UserResource::create([
        'user_id' => $user->id,
        'wood' => 0,
        'last_produced_at' => now()->subHour(),
    ]);

    $this->get(route('dashboard'))->assertOk();

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'wood' => 15,
        'lifetime_wood' => 15,
    ]);

    $this->assertDatabaseHas('resource_collections', [
        'user_id' => $user->id,
        'wood' => 15,
        'source' => 'passive',
    ]);
});

test('zero passive production advances time without writing a resource collection row', function () {
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
        'level' => 0,
        'built_at' => null,
    ]);

    $lastProducedAt = now()->subHours(2);
    UserResource::create([
        'user_id' => $user->id,
        'gold' => 0,
        'last_produced_at' => $lastProducedAt,
    ]);

    $this->get(route('dashboard'))->assertOk();

    $resources = UserResource::where('user_id', $user->id)->firstOrFail();

    expect($resources->gold)->toBe(0)
        ->and($resources->last_produced_at?->format('Y-m-d H:i:s'))->toBe($lastProducedAt->copy()->addHours(2)->format('Y-m-d H:i:s'))
        ->and(ResourceCollection::where('user_id', $user->id)->exists())->toBeFalse();
});

test('building upgrade unlocks matching achievements', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 10],
    ]);

    $building = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 0,
        'built_at' => null,
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'wood' => 10,
        'last_produced_at' => now(),
    ]);

    $achievement = Achievement::create([
        'name' => 'First Mine',
        'slug' => 'first-mine',
        'description' => 'Build the mine.',
        'type' => 'building_level',
        'building_type_id' => $mine->id,
        'target_value' => 1,
        'production_bonus_percent' => 5,
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_achievements', [
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 1,
    ]);

    expect(UserAchievement::where('user_id', $user->id)->where('achievement_id', $achievement->id)->firstOrFail()->unlocked_at)
        ->not->toBeNull();
});

test('manual collect unlocks manual collect achievements', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $achievement = Achievement::create([
        'name' => 'First Collect',
        'slug' => 'first-collect',
        'description' => 'Collect once.',
        'type' => 'manual_collects',
        'target_value' => 1,
        'production_bonus_percent' => 5,
    ]);

    $this->post(route('dashboard.collect'))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('user_achievements', [
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 1,
    ]);

    expect(UserAchievement::where('user_id', $user->id)->where('achievement_id', $achievement->id)->firstOrFail()->unlocked_at)
        ->not->toBeNull();
});

test('passive production unlocks lifetime resource achievements', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

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
        'building_type_id' => $farm->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'food' => 0,
        'last_produced_at' => now()->subHour(),
    ]);

    $achievement = Achievement::create([
        'name' => 'Food Producer',
        'slug' => 'food-producer',
        'description' => 'Produce food.',
        'type' => 'resource_lifetime',
        'resource_type' => 'food',
        'target_value' => 10,
        'production_bonus_percent' => 5,
    ]);

    $this->get(route('dashboard'))->assertOk();

    $this->assertDatabaseHas('user_achievements', [
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 10,
    ]);

    expect(UserAchievement::where('user_id', $user->id)->where('achievement_id', $achievement->id)->firstOrFail()->unlocked_at)
        ->not->toBeNull();
});

test('prestige is blocked when road max level is not configured', function () {
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
        'max_level' => null,
    ]);

    UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 60_000_000,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'wood' => 100,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.prestige'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('prestige');

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'prestiges' => 0,
    ]);
});

test('prestige keeps unlocked achievements and minigame stats while resetting buildings', function () {
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
        'max_level' => 5,
    ]);

    $mine = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 10,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    $roadBuilding = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $road->id,
        'level' => 5,
        'built_at' => now(),
    ]);

    $mineBuilding = UserBuilding::create([
        'user_id' => $user->id,
        'building_type_id' => $mine->id,
        'level' => 3,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $user->id,
        'gold' => 100,
        'wood' => 200,
        'lifetime_gold' => 500,
        'manual_collects' => 4,
        'last_produced_at' => now(),
        'last_collected_at' => now(),
    ]);

    Minigame::create([
        'user_id' => $user->id,
        'resource' => 'gold',
        'completions' => 9,
        'resources_gained' => 50,
    ]);

    $achievement = Achievement::create([
        'name' => 'Already Unlocked',
        'slug' => 'already-unlocked',
        'description' => 'Keep this.',
        'type' => 'manual_collects',
        'target_value' => 1,
        'production_bonus_percent' => 5,
    ]);

    UserAchievement::create([
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 4,
        'unlocked_at' => now(),
        'notification_seen_at' => now(),
    ]);

    $this->post(route('dashboard.prestige'))
        ->assertRedirect(route('dashboard'));

    expect($roadBuilding->fresh()->level)->toBe(0)
        ->and($roadBuilding->fresh()->built_at)->toBeNull()
        ->and($mineBuilding->fresh()->level)->toBe(0)
        ->and($mineBuilding->fresh()->built_at)->toBeNull();

    $this->assertDatabaseHas('user_resources', [
        'user_id' => $user->id,
        'gold' => 0,
        'wood' => 0,
        'lifetime_gold' => 500,
        'manual_collects' => 4,
        'prestiges' => 1,
        'last_collected_at' => null,
    ]);

    $this->assertDatabaseHas('minigames', [
        'user_id' => $user->id,
        'resource' => 'gold',
        'completions' => 9,
        'resources_gained' => 50,
    ]);

    $this->assertDatabaseHas('user_achievements', [
        'user_id' => $user->id,
        'achievement_id' => $achievement->id,
        'progress' => 4,
    ]);
});
