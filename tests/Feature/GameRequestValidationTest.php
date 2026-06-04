<?php

use App\Models\BuildingType;
use App\Models\User;
use App\Models\UserBuilding;
use App\Models\UserResource;
use App\Models\WeatherSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('weather location update validates browser supplied weather data', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from(route('immersive'))->post(route('dashboard.weather-location'), [
        'latitude' => 91,
        'longitude' => 181,
        'weather_code' => 100,
        'api_time' => 'not-a-date',
    ])
        ->assertRedirect(route('immersive'))
        ->assertSessionHasErrors(['latitude', 'longitude', 'weather_code', 'api_time']);

    expect(WeatherSnapshot::where('user_id', $user->id)->exists())->toBeFalse();
});

test('invalid minigame resources return not found and do not create progress', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/dashboard/minigames/iron/complete')
        ->assertNotFound();

    $this->assertDatabaseMissing('minigames', [
        'user_id' => $user->id,
        'resource' => 'iron',
    ]);
});

test('road build amount cannot exceed the configured request limit', function () {
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
        'wood' => 20_000_000,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->from(route('immersive'))
        ->post(route('dashboard.buildings.upgrade', $building), [
            'amount' => 10_000_001,
        ])
        ->assertRedirect(route('immersive'))
        ->assertSessionHasErrors('amount');

    expect($building->fresh()->level)->toBe(0);
});

test('road build amount must be a positive integer', function (mixed $amount) {
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
        'wood' => 100,
        'last_produced_at' => now(),
    ]);

    $this->from(route('immersive'))
        ->post(route('dashboard.buildings.upgrade', $building), [
            'amount' => $amount,
        ])
        ->assertRedirect(route('immersive'))
        ->assertSessionHasErrors('amount');

    expect($building->fresh()->level)->toBe(0);
})->with([
    'zero' => [0],
    'negative' => [-1],
    'decimal' => [1.5],
    'string' => ['many'],
]);

test('weather code validates lower bound and integer type', function (mixed $weatherCode) {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from(route('immersive'))->post(route('dashboard.weather-location'), [
        'latitude' => 54.3957,
        'longitude' => 24.0389,
        'weather_code' => $weatherCode,
        'api_time' => '2026-06-04T12:00:00Z',
    ])
        ->assertRedirect(route('immersive'))
        ->assertSessionHasErrors('weather_code');
})->with([
    'negative' => [-1],
    'decimal' => [1.5],
    'text' => ['rain'],
]);

test('weather location update requires all payload fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from(route('immersive'))->post(route('dashboard.weather-location'), [])
        ->assertRedirect(route('immersive'))
        ->assertSessionHasErrors(['latitude', 'longitude', 'weather_code', 'api_time']);
});
