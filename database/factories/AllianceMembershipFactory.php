<?php

namespace Database\Factories;

use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllianceMembership>
 */
class AllianceMembershipFactory extends Factory
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
            'role' => 'member',
            'total_contributed' => 0,
            'joined_at' => now(),
        ];
    }

    public function leader(): static
    {
        return $this->state(fn (): array => [
            'role' => 'leader',
        ]);
    }

    public function officer(): static
    {
        return $this->state(fn (): array => [
            'role' => 'officer',
        ]);
    }

    public function member(): static
    {
        return $this->state(fn (): array => [
            'role' => 'member',
        ]);
    }

    public function contributed(int $amount): static
    {
        return $this->state(fn (): array => [
            'total_contributed' => $amount,
        ]);
    }
}
