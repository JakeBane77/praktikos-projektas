<?php

namespace Database\Factories;

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAchievement>
 */
class UserAchievementFactory extends Factory
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
            'achievement_id' => Achievement::factory(),
            'progress' => 0,
            'unlocked_at' => null,
            'notification_seen_at' => null,
        ];
    }

    public function unlocked(?int $progress = null): static
    {
        return $this->state(fn (): array => [
            'progress' => $progress ?? 1,
            'unlocked_at' => now(),
        ]);
    }

    public function seen(): static
    {
        return $this->unlocked()->state(fn (): array => [
            'notification_seen_at' => now(),
        ]);
    }
}
