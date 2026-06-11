<?php

namespace Database\Factories;

use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Alliance>
 */
class AllianceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999_999),
            'description' => fake()->optional()->sentence(),
            'leader_id' => User::factory(),
            'member_limit' => 20,
            'is_open' => true,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'is_open' => true,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'is_open' => false,
        ]);
    }

    public function withLeaderMembership(): static
    {
        return $this->afterCreating(function (Alliance $alliance): void {
            AllianceMembership::factory()
                ->leader()
                ->for($alliance)
                ->for(User::query()->findOrFail($alliance->leader_id), 'user')
                ->create();
        });
    }
}
