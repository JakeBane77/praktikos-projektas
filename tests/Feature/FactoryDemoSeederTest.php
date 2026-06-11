<?php

use Database\Seeders\FactoryDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('factory demo seeder creates demo users and game state', function () {
    $this->seed(FactoryDemoSeeder::class);

    expect(DB::table('users')->count())->toBe(200)
        ->and(DB::table('user_resources')->count())->toBe(200)
        ->and(DB::table('user_buildings')->count())->toBe(1_000)
        ->and(DB::table('minigames')->count())->toBe(800)
        ->and(DB::table('building_types')->count())->toBe(5)
        ->and(DB::table('achievements')->count())->toBe(58)
        ->and(DB::table('alliances')->count())->toBe(12)
        ->and(DB::table('alliance_user')->count())->toBeGreaterThan(12)
        ->and(DB::table('alliance_goals')->count())->toBe(24)
        ->and(DB::table('alliance_goal_contributions')->count())->toBeGreaterThan(0)
        ->and(DB::table('alliance_creation_logs')->count())->toBe(12);
});
