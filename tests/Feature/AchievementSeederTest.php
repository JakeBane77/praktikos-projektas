<?php

use Database\Seeders\AchievementSeeder;
use Database\Seeders\BuildingTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('achievement seeder creates milestone achievements with default production bonuses', function () {
    $this->seed([
        BuildingTypeSeeder::class,
        AchievementSeeder::class,
    ]);

    expect(DB::table('achievements')->count())->toBe(42);

    $this->assertDatabaseHas('achievements', [
        'slug' => 'gold-1000',
        'type' => 'resource_lifetime',
        'resource_type' => 'gold',
        'target_value' => 1_000,
        'production_bonus_percent' => 5,
    ]);

    $this->assertDatabaseHas('achievements', [
        'slug' => 'mine-level-25',
        'type' => 'building_level',
        'target_value' => 25,
        'production_bonus_percent' => 5,
    ]);

    $this->assertDatabaseHas('achievements', [
        'slug' => 'connect-the-whole-planet',
        'name' => 'Connect the whole planet',
        'description' => 'You have connected the entire planet with roads',
        'type' => 'road_length',
        'target_value' => 60_000_000,
        'production_bonus_percent' => 5,
    ]);

    $this->assertDatabaseHas('achievements', [
        'slug' => 'manual-collects-1000',
        'type' => 'manual_collects',
        'target_value' => 1_000,
        'production_bonus_percent' => 5,
    ]);
});
