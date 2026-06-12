<?php

namespace Database\Factories;

use App\Models\Alliance;
use App\Models\AllianceChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllianceChatMessage>
 */
class AllianceChatMessageFactory extends Factory
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
            'message' => fake()->sentence(),
        ];
    }
}
