<?php

use App\Models\BuildingType;
use App\Models\ResourceCollection;
use App\Models\User;
use App\Models\UserBuilding;
use App\Models\UserResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        'wood' => 16,
        'stone' => 0,
        'food' => 10,
        'lifetime_gold' => 12,
        'lifetime_wood' => 16,
        'lifetime_stone' => 0,
        'lifetime_food' => 10,
        'manual_collects' => 1,
    ]);

    $this->assertDatabaseHas('resource_collections', [
        'user_id' => $user->id,
        'gold' => 12,
        'wood' => 16,
        'stone' => 0,
        'food' => 10,
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

    expect($resources->wood)->toBe(10)
        ->and($resources->food)->toBe(16)
        ->and($resources->manual_collects)->toBe(1);

    expect(ResourceCollection::where('user_id', $user->id)->count())->toBe(1);
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
        'wood' => 10,
        'stone' => 0,
        'food' => 10,
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

    $this->get(route('dashboard'))->assertOk();

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

    $tiedUser = User::factory()->create();
    UserBuilding::create([
        'user_id' => $tiedUser->id,
        'building_type_id' => $road->id,
        'level' => 4,
        'built_at' => now(),
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
            ->where('roadStats.rank', 2)
        );
});
