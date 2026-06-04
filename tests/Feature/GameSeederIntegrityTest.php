<?php

use Database\Seeders\AchievementSeeder;
use Database\Seeders\BuildingTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('building type seeder creates the expected default game configuration', function () {
    $this->seed(BuildingTypeSeeder::class);

    expect(DB::table('building_types')->count())->toBe(5);

    $this->assertDatabaseHas('building_types', [
        'slug' => 'lumbercamp',
        'produces_resource' => 'wood',
        'base_production_per_hour' => 15,
        'upgrade_cost_multiplier' => 1.8,
        'max_level' => 25,
    ]);

    $this->assertDatabaseHas('building_types', [
        'slug' => 'road',
        'base_production_per_hour' => 0,
        'upgrade_cost_multiplier' => 1,
        'max_level' => 40_000_000,
    ]);

    expect(json_decode(DB::table('building_types')->where('slug', 'mine')->value('base_costs'), true))
        ->toBe([
            'wood' => 20,
            'food' => 20,
            'stone' => 45,
        ]);
});

test('building and achievement seeders are idempotent', function () {
    $this->seed(BuildingTypeSeeder::class);
    $this->seed(AchievementSeeder::class);
    $this->seed(BuildingTypeSeeder::class);
    $this->seed(AchievementSeeder::class);

    expect(DB::table('building_types')->count())->toBe(5)
        ->and(DB::table('achievements')->count())->toBe(58);
});
