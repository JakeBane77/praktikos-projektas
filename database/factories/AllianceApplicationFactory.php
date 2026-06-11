<?php

namespace Database\Factories;

use App\Models\Alliance;
use App\Models\AllianceApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllianceApplication>
 */
class AllianceApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alliance_id' => Alliance::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function forPrivateAlliance(): static
    {
        return $this->state(fn (): array => [
            'alliance_id' => Alliance::factory()->closed(),
        ]);
    }
}
