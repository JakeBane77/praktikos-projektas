<?php

namespace Database\Factories;

use App\Models\ResourceCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceCollection>
 */
class ResourceCollectionFactory extends Factory
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
            'source' => 'manual',
            'collected_at' => now(),
        ];
    }

    public function amounts(int $gold = 0, int $wood = 0, int $stone = 0, int $food = 0): static
    {
        return $this->state(fn (): array => [
            'gold' => $gold,
            'wood' => $wood,
            'stone' => $stone,
            'food' => $food,
        ]);
    }

    public function source(string $source): static
    {
        return $this->state(fn (): array => [
            'source' => $source,
        ]);
    }
}
