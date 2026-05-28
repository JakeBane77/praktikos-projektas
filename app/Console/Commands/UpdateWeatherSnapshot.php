<?php

namespace App\Console\Commands;

use App\Models\WeatherSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateWeatherSnapshot extends Command
{
    private const LATITUDE = 54.3957;

    private const LONGITUDE = 24.0389;

    protected $signature = 'weather:update';

    protected $description = 'Fetch the current Open-Meteo weather code and store it in the database.';

    public function handle(): int
    {
        $response = Http::timeout(10)
            ->withoutVerifying()
            ->retry(2, 500)
            ->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => self::LATITUDE,
                'longitude' => self::LONGITUDE,
                'current' => 'weather_code',
                'timezone' => 'auto',
            ])
            ->throw()
            ->json();

        $current = $response['current'] ?? [];

        WeatherSnapshot::updateOrCreate(
            [
                'latitude' => self::LATITUDE,
                'longitude' => self::LONGITUDE,
            ],
            [
                'weather_code' => isset($current['weather_code'])
                    ? (int) $current['weather_code']
                    : null,
                'api_time' => $current['time'] ?? null,
            ],
        );

        $this->info('Weather snapshot updated.');

        return self::SUCCESS;
    }
}
