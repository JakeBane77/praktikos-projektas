<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WeatherSnapshot;
use App\Support\Weather;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeatherSnapshot>
 */
class WeatherSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'weather_code' => fake()->randomElement([0, 1, 2, 3, 45, 61, 71, 95]),
            'api_time' => now(),
        ];
    }

    public function defaultLocation(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'latitude' => Weather::LATITUDE,
            'longitude' => Weather::LONGITUDE,
        ]);
    }

    public function forUser(User|Factory $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user,
        ]);
    }
}
