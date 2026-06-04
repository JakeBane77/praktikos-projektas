<?php

namespace App\Services;

use App\Models\WeatherSnapshot;
use App\Support\Weather;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class WeatherSnapshotService
{
    public function update(float $latitude, float $longitude, ?int $userId = null): WeatherSnapshot
    {
        $latitude = Weather::normalizeCoordinate($latitude);
        $longitude = Weather::normalizeCoordinate($longitude);
        $currentWeather = $this->fetchCurrentWeather($latitude, $longitude);

        return WeatherSnapshot::updateOrCreate(
            $userId === null
                ? [
                    'user_id' => null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]
                : [
                    'user_id' => $userId,
                ],
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'weather_code' => $currentWeather['weather_code'],
                'api_time' => $currentWeather['api_time'],
            ],
        );
    }

    public function updateStoredSnapshots(): int
    {
        $this->update(Weather::LATITUDE, Weather::LONGITUDE);

        return 1;
    }

    /**
     * @return array{weather_code: int|null, api_time: CarbonImmutable|null}
     */
    private function fetchCurrentWeather(float $latitude, float $longitude): array
    {
        $response = Http::timeout(10)
            ->withoutVerifying()
            ->retry(2, 500)
            ->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'weather_code',
                'timezone' => 'auto',
            ])
            ->throw()
            ->json();

        $current = $response['current'] ?? [];
        $apiTimezone = $response['timezone'] ?? config('app.timezone');
        $apiTime = isset($current['time'])
            ? CarbonImmutable::parse($current['time'], $apiTimezone)
                ->setTimezone(config('app.timezone'))
            : null;

        return [
            'weather_code' => isset($current['weather_code'])
                ? (int) $current['weather_code']
                : null,
            'api_time' => $apiTime,
        ];
    }
}
