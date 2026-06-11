<?php

namespace Database\Factories;

use App\Models\AllianceGoal;
use App\Models\AllianceGoalContribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllianceGoalContribution>
 */
class AllianceGoalContributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alliance_goal_id' => AllianceGoal::factory(),
            'user_id' => User::factory(),
            'resource_type' => fake()->randomElement(['gold', 'wood', 'stone', 'food']),
            'amount' => fake()->numberBetween(1, 10_000),
            'created_at' => now(),
        ];
    }

    public function forResource(string $resource): static
    {
        return $this->state(fn (): array => [
            'resource_type' => $resource,
        ]);
    }

    public function amount(int $amount): static
    {
        return $this->state(fn (): array => [
            'amount' => $amount,
        ]);
    }
}
