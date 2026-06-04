<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserResource>
 */
class UserResourceFactory extends Factory
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
            'gold' => 0,
            'wood' => 0,
            'stone' => 0,
            'food' => 0,
            'lifetime_gold' => 0,
            'lifetime_wood' => 0,
            'lifetime_stone' => 0,
            'lifetime_food' => 0,
            'manual_collects' => 0,
            'prestiges' => 0,
            'last_produced_at' => now(),
            'last_collected_at' => null,
        ];
    }

    public function withResources(int $gold = 0, int $wood = 0, int $stone = 0, int $food = 0): static
    {
        return $this->state(fn (): array => [
            'gold' => $gold,
            'wood' => $wood,
            'stone' => $stone,
            'food' => $food,
        ]);
    }

    public function withLifetime(int $gold = 0, int $wood = 0, int $stone = 0, int $food = 0): static
    {
        return $this->state(fn (): array => [
            'lifetime_gold' => $gold,
            'lifetime_wood' => $wood,
            'lifetime_stone' => $stone,
            'lifetime_food' => $food,
        ]);
    }

    public function collectedToday(): static
    {
        return $this->state(fn (): array => [
            'last_collected_at' => now(),
        ]);
    }

    public function producedAt(mixed $time): static
    {
        return $this->state(fn (): array => [
            'last_produced_at' => $time,
        ]);
    }
}
