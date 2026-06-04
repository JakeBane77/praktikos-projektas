<?php

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users cannot mark another users achievement popup as seen', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $achievement = Achievement::create([
        'name' => 'Owner Achievement',
        'slug' => 'owner-achievement',
        'description' => 'Belongs to another user.',
        'type' => 'manual_collects',
        'target_value' => 1,
        'production_bonus_percent' => 5,
    ]);

    $userAchievement = UserAchievement::create([
        'user_id' => $owner->id,
        'achievement_id' => $achievement->id,
        'progress' => 1,
        'unlocked_at' => now(),
        'notification_seen_at' => null,
    ]);

    $this->post(route('dashboard.achievements.unlocks.seen'), [
        'ids' => [$userAchievement->id],
    ])->assertForbidden();

    expect($userAchievement->fresh()->notification_seen_at)->toBeNull();
});
