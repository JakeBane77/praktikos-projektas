<?php

namespace Database\Factories;

use App\Models\Minigame;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Minigame>
 */
class MinigameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'resource' => fake()->randomElement(['gold', 'wood', 'stone', 'food']),
            'completions' => 0,
            'resources_gained' => 0,
        ];
    }

    public function resource(string $resource): static
    {
        return $this->state(fn (): array => [
            'resource' => $resource,
        ]);
    }

    public function completed(int $completions, int $resourcesGained = 0): static
    {
        return $this->state(fn (): array => [
            'completions' => $completions,
            'resources_gained' => $resourcesGained,
        ]);
    }
}
