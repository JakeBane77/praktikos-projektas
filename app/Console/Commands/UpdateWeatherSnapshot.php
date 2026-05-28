<?php

namespace App\Console\Commands;

use App\Models\WeatherSnapshot;
use App\Support\Weather;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateWeatherSnapshot extends Command
{
    protected $signature = 'weather:update';

    protected $description = 'Fetch the current Open-Meteo weather code and store it in the database.';

    public function handle(): int
    {
        $response = Http::timeout(10)
            ->withoutVerifying()
            ->retry(2, 500)
            ->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => Weather::LATITUDE,
                'longitude' => Weather::LONGITUDE,
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

        WeatherSnapshot::updateOrCreate(
            [
                'latitude' => Weather::LATITUDE,
                'longitude' => Weather::LONGITUDE,
            ],
            [
                'weather_code' => isset($current['weather_code'])
                    ? (int) $current['weather_code']
                    : null,
                'api_time' => $apiTime,
            ],
        );

        $this->info('Weather snapshot updated.');

        return self::SUCCESS;
    }
}
