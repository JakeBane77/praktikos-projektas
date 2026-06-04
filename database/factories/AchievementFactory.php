<?php

namespace Database\Factories;

use App\Models\Achievement;
use App\Models\BuildingType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'building_type_id' => null,
            'bonus_building_type_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999_999),
            'description' => fake()->sentence(),
            'type' => 'manual_collects',
            'resource_type' => null,
            'target_value' => fake()->numberBetween(1, 100),
            'reward_gold' => 0,
            'reward_wood' => 0,
            'reward_stone' => 0,
            'reward_food' => 0,
            'production_bonus_percent' => 5,
        ];
    }

    public function forResource(string $resource, int $targetValue = 1_000): static
    {
        return $this->state(fn (): array => [
            'type' => 'resource_lifetime',
            'resource_type' => $resource,
            'target_value' => $targetValue,
        ]);
    }

    public function forBuildingLevel(BuildingType|Factory $buildingType, int $targetLevel = 1): static
    {
        return $this->state(fn (): array => [
            'building_type_id' => $buildingType,
            'type' => 'building_level',
            'target_value' => $targetLevel,
        ]);
    }

    public function withProductionBonus(int $percent, BuildingType|Factory|null $buildingType = null): static
    {
        return $this->state(fn (): array => [
            'production_bonus_percent' => $percent,
            'bonus_building_type_id' => $buildingType,
        ]);
    }
}
