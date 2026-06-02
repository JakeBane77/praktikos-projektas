<?php

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

    $this->post(route('dashboard.weather-location'), [
        'latitude' => 55.1234,
        'longitude' => 24.9876,
        'weather_code' => 45,
        'api_time' => '2026-05-29T06:18Z',
    ])->assertRedirect(route('dashboard'));

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

    $this->post(route('dashboard.weather-location.default'))
        ->assertRedirect(route('dashboard'));

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
            ->where('minigames.0.resourcesGained', 6));
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
