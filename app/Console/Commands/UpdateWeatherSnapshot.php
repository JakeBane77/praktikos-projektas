<?php

namespace App\Console\Commands;

use App\Services\WeatherSnapshotService;
use App\Support\Weather;
use Illuminate\Console\Command;

class UpdateWeatherSnapshot extends Command
{
    protected $signature = 'weather:update';

    protected $description = 'Fetch the current Open-Meteo weather code and store it in the database.';

    public function handle(WeatherSnapshotService $weatherSnapshots): int
    {
        $weatherSnapshots->update(Weather::LATITUDE, Weather::LONGITUDE);

        $this->info('Weather snapshot updated.');

        return self::SUCCESS;
    }
}
