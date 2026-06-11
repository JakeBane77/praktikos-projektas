<?php

use App\Models\Alliance;
use App\Models\AllianceApplication;
use App\Models\AllianceCreationLog;
use App\Models\AllianceGoal;
use App\Models\AllianceGoalContribution;
use App\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('alliance feature factories create valid related records', function () {
    $alliance = Alliance::factory()
        ->closed()
        ->withLeaderMembership()
        ->create();

    $officerMembership = AllianceMembership::factory()
        ->officer()
        ->contributed(500)
        ->for($alliance)
        ->create();

    $application = AllianceApplication::factory()
        ->for($alliance)
        ->create();

    $goal = AllianceGoal::factory()
        ->for($alliance)
        ->forResource('wood')
        ->withProgress(1_000)
        ->withStages([50, 100], [1, 2])
        ->create();

    $contribution = AllianceGoalContribution::factory()
        ->for($goal, 'goal')
        ->for($officerMembership->user()->firstOrFail(), 'user')
        ->forResource('wood')
        ->amount(500)
        ->create();

    $creationLog = AllianceCreationLog::factory()
        ->for($alliance->leader()->firstOrFail(), 'user')
        ->create();

    expect($alliance->is_open)->toBeFalse()
        ->and($alliance->memberships()->where('role', 'leader')->exists())->toBeTrue()
        ->and($officerMembership->role)->toBe('officer')
        ->and($officerMembership->total_contributed)->toBe(500)
        ->and($application->alliance_id)->toBe($alliance->id)
        ->and($goal->resource_type)->toBe('wood')
        ->and($goal->stage_percentages)->toBe([50, 100])
        ->and($goal->stage_donor_requirements)->toBe([1, 2])
        ->and($contribution->resource_type)->toBe('wood')
        ->and($contribution->amount)->toBe(500)
        ->and($creationLog->user_id)->toBe($alliance->leader_id);
});
