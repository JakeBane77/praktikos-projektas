<?php

namespace Database\Factories;

use App\Models\AllianceCreationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllianceCreationLog>
 */
class AllianceCreationLogFactory extends Factory
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
            'created_at' => now(),
        ];
    }

    public function createdAt(mixed $time): static
    {
        return $this->state(fn (): array => [
            'created_at' => $time,
        ]);
    }
}
