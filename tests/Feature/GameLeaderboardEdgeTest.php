<?php

use App\Models\Minigame;
use App\Models\User;
use App\Models\UserResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('leaderboards assign tied ranks and only include the top fifty entries', function () {
    $currentUser = User::factory()->create(['name' => 'Current Player']);
    $this->actingAs($currentUser);

    UserResource::create([
        'user_id' => $currentUser->id,
        'prestiges' => 10,
        'manual_collects' => 1,
        'last_produced_at' => now(),
    ]);

    $leaderOne = User::factory()->create(['name' => 'Leader One']);
    UserResource::create([
        'user_id' => $leaderOne->id,
        'prestiges' => 20,
        'last_produced_at' => now(),
    ]);

    $leaderTwo = User::factory()->create(['name' => 'Leader Two']);
    UserResource::create([
        'user_id' => $leaderTwo->id,
        'prestiges' => 20,
        'last_produced_at' => now(),
    ]);

    for ($index = 0; $index < 55; $index += 1) {
        $user = User::factory()->create();
        UserResource::create([
            'user_id' => $user->id,
            'prestiges' => 9 - $index,
            'last_produced_at' => now(),
        ]);
    }

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaderboards.boards.0.entries.0.rank', 1)
            ->where('leaderboards.boards.0.entries.0.userName', 'Leader One')
            ->where('leaderboards.boards.0.entries.1.rank', 1)
            ->where('leaderboards.boards.0.entries.1.userName', 'Leader Two')
            ->where('leaderboards.boards.0.entries.2.rank', 3)
            ->where('leaderboards.boards.0.entries.2.userName', 'Current Player')
            ->has('leaderboards.boards.0.entries', 50)
        );
});

test('minigame leaderboards are ordered independently for each resource', function () {
    $currentUser = User::factory()->create(['name' => 'Current Player']);
    $this->actingAs($currentUser);

    UserResource::create([
        'user_id' => $currentUser->id,
        'last_produced_at' => now(),
    ]);

    Minigame::create([
        'user_id' => $currentUser->id,
        'resource' => 'wood',
        'completions' => 3,
        'resources_gained' => 5,
    ]);

    Minigame::create([
        'user_id' => $currentUser->id,
        'resource' => 'food',
        'completions' => 8,
        'resources_gained' => 10,
    ]);

    $woodLeader = User::factory()->create(['name' => 'Wood Leader']);
    UserResource::create([
        'user_id' => $woodLeader->id,
        'last_produced_at' => now(),
    ]);
    Minigame::create([
        'user_id' => $woodLeader->id,
        'resource' => 'wood',
        'completions' => 9,
        'resources_gained' => 20,
    ]);

    $foodLeader = User::factory()->create(['name' => 'Food Leader']);
    UserResource::create([
        'user_id' => $foodLeader->id,
        'last_produced_at' => now(),
    ]);
    Minigame::create([
        'user_id' => $foodLeader->id,
        'resource' => 'food',
        'completions' => 12,
        'resources_gained' => 25,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaderboards.boards.2.key', 'wood_minigame')
            ->where('leaderboards.boards.2.entries.0.userName', 'Wood Leader')
            ->where('leaderboards.boards.2.currentRank', 2)
            ->where('leaderboards.boards.3.key', 'food_minigame')
            ->where('leaderboards.boards.3.entries.0.userName', 'Food Leader')
            ->where('leaderboards.boards.3.currentRank', 2)
        );
});
