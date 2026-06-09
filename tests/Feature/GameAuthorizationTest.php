<?php

use App\Models\Achievement;
use App\Models\Alliance;
use App\Models\AllianceGoal;
use App\Models\AllianceMembership;
use App\Models\BuildingType;
use App\Models\Minigame;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBuilding;
use App\Models\UserResource;
use App\Models\WeatherSnapshot;
use Inertia\Testing\AssertableInertia as Assert;

test('game policies allow access only to owned game state', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $buildingType = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    $building = UserBuilding::create([
        'user_id' => $owner->id,
        'building_type_id' => $buildingType->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    $resources = UserResource::create([
        'user_id' => $owner->id,
        'gold' => 0,
        'wood' => 0,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $minigame = Minigame::create([
        'user_id' => $owner->id,
        'resource' => 'wood',
        'completions' => 0,
        'resources_gained' => 0,
    ]);

    $achievement = Achievement::create([
        'name' => 'Collector',
        'slug' => 'collector',
        'type' => 'manual_collects',
        'target_value' => 1,
    ]);

    $userAchievement = UserAchievement::create([
        'user_id' => $owner->id,
        'achievement_id' => $achievement->id,
        'progress' => 1,
        'unlocked_at' => now(),
    ]);

    $weatherSnapshot = WeatherSnapshot::create([
        'user_id' => $owner->id,
        'latitude' => 54.3957,
        'longitude' => 24.0389,
        'weather_code' => 0,
        'api_time' => now(),
    ]);

    expect($owner->can('upgrade', $building))->toBeTrue()
        ->and($otherUser->can('upgrade', $building))->toBeFalse()
        ->and($owner->can('collect', $resources))->toBeTrue()
        ->and($otherUser->can('collect', $resources))->toBeFalse()
        ->and($owner->can('prestige', $resources))->toBeTrue()
        ->and($otherUser->can('prestige', $resources))->toBeFalse()
        ->and($owner->can('complete', $minigame))->toBeTrue()
        ->and($otherUser->can('complete', $minigame))->toBeFalse()
        ->and($owner->can('markUnlockSeen', $userAchievement))->toBeTrue()
        ->and($otherUser->can('markUnlockSeen', $userAchievement))->toBeFalse()
        ->and($owner->can('update', $weatherSnapshot))->toBeTrue()
        ->and($otherUser->can('update', $weatherSnapshot))->toBeFalse()
        ->and($owner->can('delete', $weatherSnapshot))->toBeTrue()
        ->and($otherUser->can('delete', $weatherSnapshot))->toBeFalse()
        ->and($otherUser->can('create', WeatherSnapshot::class))->toBeTrue();
});

test('users cannot upgrade another users building', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $buildingType = BuildingType::create([
        'name' => 'Mine',
        'slug' => 'mine',
        'produces_resource' => 'gold',
        'base_production_per_hour' => 5,
        'production_multiplier' => 1,
        'base_costs' => ['wood' => 100],
    ]);

    $building = UserBuilding::create([
        'user_id' => $owner->id,
        'building_type_id' => $buildingType->id,
        'level' => 1,
        'built_at' => now(),
    ]);

    UserResource::create([
        'user_id' => $otherUser->id,
        'gold' => 0,
        'wood' => 1_000,
        'stone' => 0,
        'food' => 0,
        'last_produced_at' => now(),
    ]);

    $this->post(route('dashboard.buildings.upgrade', $building))
        ->assertForbidden();

    expect($building->fresh()->level)->toBe(1);
});

test('alliance policies enforce leader and officer permissions', function () {
    $leader = User::factory()->create();
    $officer = User::factory()->create();
    $member = User::factory()->create();
    $outsideUser = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Stone Guard',
        'slug' => 'stone-guard',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $leader->id,
        'role' => 'leader',
        'joined_at' => now(),
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $officer->id,
        'role' => 'officer',
        'joined_at' => now(),
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $member->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $goal = AllianceGoal::create([
        'alliance_id' => $alliance->id,
        'name' => 'Wood Stockpile',
        'resource_type' => 'wood',
        'target_amount' => 1_000,
        'current_amount' => 0,
        'production_bonus_percent' => 5,
        'bonus_duration_hours' => 24,
        'status' => 'active',
    ]);

    expect($leader->can('update', $alliance))->toBeTrue()
        ->and($leader->can('delete', $alliance))->toBeTrue()
        ->and($leader->can('updateVisibility', $alliance))->toBeTrue()
        ->and($officer->can('update', $alliance))->toBeFalse()
        ->and($officer->can('delete', $alliance))->toBeFalse()
        ->and($officer->can('updateVisibility', $alliance))->toBeTrue()
        ->and($member->can('updateVisibility', $alliance))->toBeFalse()
        ->and($outsideUser->can('updateVisibility', $alliance))->toBeFalse()
        ->and($leader->can('kick', [$alliance, $officer->allianceMembership]))->toBeTrue()
        ->and($leader->can('kick', [$alliance, $member->allianceMembership]))->toBeTrue()
        ->and($officer->can('kick', [$alliance, $member->allianceMembership]))->toBeTrue()
        ->and($officer->can('kick', [$alliance, $leader->allianceMembership]))->toBeFalse()
        ->and($officer->can('kick', [$alliance, $officer->allianceMembership]))->toBeFalse()
        ->and($member->can('kick', [$alliance, $officer->allianceMembership]))->toBeFalse()
        ->and($leader->can('promote', [$alliance, $member->allianceMembership]))->toBeTrue()
        ->and($leader->can('demote', [$alliance, $officer->allianceMembership]))->toBeTrue()
        ->and($leader->can('transferLeadership', [$alliance, $officer->allianceMembership]))->toBeTrue()
        ->and($leader->can('transferLeadership', [$alliance, $member->allianceMembership]))->toBeTrue()
        ->and($officer->can('promote', [$alliance, $member->allianceMembership]))->toBeFalse()
        ->and($member->can('demote', [$alliance, $officer->allianceMembership]))->toBeFalse()
        ->and($officer->can('transferLeadership', [$alliance, $member->allianceMembership]))->toBeFalse()
        ->and($member->can('transferLeadership', [$alliance, $officer->allianceMembership]))->toBeFalse()
        ->and($member->can('contribute', $goal))->toBeTrue()
        ->and($outsideUser->can('contribute', $goal))->toBeFalse();
});

test('officers can only update alliance visibility through the controller', function () {
    $leader = User::factory()->create();
    $officer = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'River Pact',
        'slug' => 'river-pact',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $leader->id,
        'role' => 'leader',
        'joined_at' => now(),
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $officer->id,
        'role' => 'officer',
        'joined_at' => now(),
    ]);

    $this->actingAs($officer)
        ->patch(route('alliances.update', $alliance), [
            'is_open' => false,
        ])
        ->assertRedirect();

    expect($alliance->fresh()->is_open)->toBeFalse();

    $this->actingAs($officer)
        ->patch(route('alliances.update', $alliance), [
            'name' => 'Officer Rename',
        ])
        ->assertForbidden();

    expect($alliance->fresh()->name)->toBe('River Pact');
});

test('leader can update alliance description and visibility through the controller', function () {
    $leader = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Silver Accord',
        'slug' => 'silver-accord',
        'description' => 'Original description',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $leader->id,
        'role' => 'leader',
        'joined_at' => now(),
    ]);

    $this->actingAs($leader)
        ->patch(route('alliances.update', $alliance), [
            'description' => 'Updated alliance description',
            'is_open' => false,
        ])
        ->assertRedirect();

    $freshAlliance = $alliance->fresh();

    expect($freshAlliance)->not->toBeNull()
        ->and($freshAlliance->description)->toBe('Updated alliance description')
        ->and($freshAlliance->is_open)->toBeFalse();
});

test('leader and officer can kick allowed alliance members', function () {
    $leader = User::factory()->create();
    $officer = User::factory()->create();
    $member = User::factory()->create();
    $secondMember = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Hill Banner',
        'slug' => 'hill-banner',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $leader->id,
        'role' => 'leader',
        'joined_at' => now(),
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $officer->id,
        'role' => 'officer',
        'joined_at' => now(),
    ]);

    $memberMembership = AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $member->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $secondMemberMembership = AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $secondMember->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $this->actingAs($officer)
        ->delete(route('alliances.members.kick', [$alliance, $memberMembership]))
        ->assertRedirect();

    expect($memberMembership->fresh())->toBeNull();

    $this->actingAs($leader)
        ->delete(route('alliances.members.kick', [$alliance, $secondMemberMembership]))
        ->assertRedirect();

    expect($secondMemberMembership->fresh())->toBeNull();
});

test('members can leave alliance but leaders must transfer or disband before leaving', function () {
    $leader = User::factory()->create();
    $member = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Oak Circle',
        'slug' => 'oak-circle',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $leader->id,
        'role' => 'leader',
        'joined_at' => now(),
    ]);

    $memberMembership = AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $member->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    expect($member->can('leave', $alliance))->toBeTrue()
        ->and($leader->can('leave', $alliance))->toBeTrue();

    $this->actingAs($leader)
        ->delete(route('alliances.leave', $alliance))
        ->assertSessionHasErrors(['alliance']);

    $this->actingAs($member)
        ->delete(route('alliances.leave', $alliance))
        ->assertRedirect();

    expect($memberMembership->fresh())->toBeNull();
});

test('leader can disband alliance only when no other members remain', function () {
    $leader = User::factory()->create();
    $member = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Last Banner',
        'slug' => 'last-banner',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $leader->id,
        'role' => 'leader',
        'joined_at' => now(),
    ]);

    $memberMembership = AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $member->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $this->actingAs($leader)
        ->delete(route('alliances.destroy', $alliance))
        ->assertSessionHasErrors(['alliance']);

    expect($alliance->fresh())->not->toBeNull();

    $memberMembership->delete();

    $this->actingAs($leader)
        ->delete(route('alliances.destroy', $alliance))
        ->assertRedirect();

    expect($alliance->fresh())->toBeNull();
});

test('leader can promote and demote alliance members', function () {
    $leader = User::factory()->create();
    $member = User::factory()->create();
    $officer = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Sun Foundry',
        'slug' => 'sun-foundry',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $leader->id,
        'role' => 'leader',
        'joined_at' => now(),
    ]);

    $memberMembership = AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $member->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $officerMembership = AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $officer->id,
        'role' => 'officer',
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->patch(route('alliances.members.promote', [$alliance, $memberMembership]))
        ->assertForbidden();

    expect($memberMembership->fresh()->role)->toBe('member');

    $this->actingAs($leader)
        ->patch(route('alliances.members.promote', [$alliance, $memberMembership]))
        ->assertRedirect();

    expect($memberMembership->fresh()->role)->toBe('officer');

    $this->actingAs($leader)
        ->patch(route('alliances.members.demote', [$alliance, $officerMembership]))
        ->assertRedirect();

    expect($officerMembership->fresh()->role)->toBe('member');
});

test('leader can transfer alliance leadership to another member', function () {
    $leader = User::factory()->create();
    $member = User::factory()->create();
    $outsideUser = User::factory()->create();

    $alliance = Alliance::create([
        'name' => 'Crown Forge',
        'slug' => 'crown-forge',
        'leader_id' => $leader->id,
        'member_limit' => 20,
        'is_open' => true,
    ]);

    $leaderMembership = AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $leader->id,
        'role' => 'leader',
        'total_contributed' => 1_000,
        'joined_at' => now(),
    ]);

    $memberMembership = AllianceMembership::create([
        'alliance_id' => $alliance->id,
        'user_id' => $member->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->patch(route('alliances.members.transfer-leadership', [$alliance, $leaderMembership]))
        ->assertForbidden();

    $this->actingAs($outsideUser)
        ->patch(route('alliances.members.transfer-leadership', [$alliance, $memberMembership]))
        ->assertForbidden();

    $this->actingAs($leader)
        ->patch(route('alliances.members.transfer-leadership', [$alliance, $memberMembership]))
        ->assertRedirect();

    expect($alliance->fresh()->leader_id)->toBe($member->id)
        ->and($leaderMembership->fresh()->role)->toBe('officer')
        ->and($memberMembership->fresh()->role)->toBe('leader');

    $this->actingAs($leader)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('alliances.current.leaderName', $member->name)
            ->where('alliances.current.members.0.userId', $member->id)
            ->where('alliances.current.members.0.role', 'leader')
            ->where('alliances.current.members.1.userId', $leader->id)
            ->where('alliances.current.members.1.role', 'officer')
        );
});
