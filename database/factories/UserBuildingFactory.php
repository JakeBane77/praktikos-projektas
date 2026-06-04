<?php

namespace Database\Factories;

use App\Models\BuildingType;
use App\Models\User;
use App\Models\UserBuilding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBuilding>
 */
class UserBuildingFactory extends Factory
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
            'building_type_id' => BuildingType::factory(),
            'level' => 1,
            'built_at' => now(),
        ];
    }

    public function level(int $level): static
    {
        return $this->state(fn (): array => [
            'level' => $level,
            'built_at' => $level > 0 ? now() : null,
        ]);
    }

    public function unbuilt(): static
    {
        return $this->state(fn (): array => [
            'level' => 0,
            'built_at' => null,
        ]);
    }

    public function forRoad(?int $maxLevel = 40_000_000): static
    {
        return $this->for(BuildingType::factory()->road($maxLevel), 'buildingType');
    }
}
