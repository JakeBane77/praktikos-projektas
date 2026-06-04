<?php

namespace Database\Factories;

use App\Models\BuildingType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BuildingType>
 */
class BuildingTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999_999),
            'produces_resource' => fake()->randomElement(['gold', 'wood', 'stone', 'food']),
            'base_production_per_hour' => fake()->numberBetween(1, 25),
            'production_multiplier' => fake()->randomFloat(2, 1.1, 1.8),
            'effect_type' => null,
            'effects' => null,
            'base_costs' => [
                fake()->randomElement(['gold', 'wood', 'stone', 'food']) => fake()->numberBetween(10, 100),
            ],
            'upgrade_cost_multiplier' => fake()->randomFloat(2, 1.2, 2.0),
            'max_level' => 25,
        ];
    }

    public function producing(string $resource): static
    {
        return $this->state(fn (array $attributes): array => [
            'produces_resource' => $resource,
            'effect_type' => null,
            'base_production_per_hour' => $attributes['base_production_per_hour'] ?? 10,
            'production_multiplier' => $attributes['production_multiplier'] ?? 1.5,
        ]);
    }

    public function road(?int $maxLevel = 40_000_000): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Road',
            'slug' => 'road-'.fake()->unique()->numberBetween(1, 999_999),
            'produces_resource' => null,
            'base_production_per_hour' => 0,
            'production_multiplier' => null,
            'effect_type' => 'road_length',
            'base_costs' => [
                'wood' => 10,
                'food' => 14,
                'stone' => 18,
                'gold' => 30,
            ],
            'upgrade_cost_multiplier' => 1,
            'max_level' => $maxLevel,
        ]);
    }

    public function withCosts(array $costs): static
    {
        return $this->state(fn (): array => [
            'base_costs' => $costs,
        ]);
    }
}
