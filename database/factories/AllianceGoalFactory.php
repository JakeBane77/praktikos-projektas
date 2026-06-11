<?php

namespace Database\Factories;

use App\Models\Alliance;
use App\Models\AllianceGoal;
use App\Services\AllianceGoalService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllianceGoal>
 */
class AllianceGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weekStartsAt = now()->startOfWeek()->startOfDay();

        return [
            'alliance_id' => Alliance::factory(),
            'name' => 'Weekly stockpile',
            'resource_type' => null,
            'target_amount' => AllianceGoalService::DEFAULT_TARGET_AMOUNT,
            'current_amount' => 0,
            'production_bonus_percent' => AllianceGoalService::DEFAULT_PRODUCTION_BONUS_PERCENT,
            'stage_percentages' => AllianceGoalService::DEFAULT_STAGE_PERCENTAGES,
            'stage_donor_requirements' => AllianceGoalService::DEFAULT_STAGE_DONOR_REQUIREMENTS,
            'week_starts_at' => $weekStartsAt,
            'week_ends_at' => $weekStartsAt->copy()->addWeek()->subSecond(),
            'status' => 'active',
            'completed_at' => null,
        ];
    }

    public function forResource(string $resource): static
    {
        return $this->state(fn (): array => [
            'resource_type' => $resource,
            'name' => ucfirst($resource).' stockpile',
        ]);
    }

    public function withProgress(int $currentAmount): static
    {
        return $this->state(fn (): array => [
            'current_amount' => $currentAmount,
        ]);
    }

    /**
     * @param  list<float|int>  $percentages
     * @param  list<int>  $donorRequirements
     */
    public function withStages(array $percentages, array $donorRequirements): static
    {
        return $this->state(fn (): array => [
            'stage_percentages' => $percentages,
            'stage_donor_requirements' => $donorRequirements,
        ]);
    }

    public function previousWeek(): static
    {
        return $this->state(function (): array {
            $weekStartsAt = now()->startOfWeek()->startOfDay()->subWeek();

            return [
                'week_starts_at' => $weekStartsAt,
                'week_ends_at' => $weekStartsAt->copy()->addWeek()->subSecond(),
                'status' => 'expired',
            ];
        });
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => 'expired',
            'completed_at' => null,
        ]);
    }
}
