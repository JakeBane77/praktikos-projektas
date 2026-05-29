<?php

namespace App\Console\Commands;

use App\Services\WeatherSnapshotService;
use Illuminate\Console\Command;

class UpdateWeatherSnapshot extends Command
{
    protected $signature = 'weather:update';

    protected $description = 'Fetch the current Open-Meteo weather code for the default location and store it in the database.';

    public function handle(WeatherSnapshotService $weatherSnapshots): int
    {
        $updatedSnapshots = $weatherSnapshots->updateStoredSnapshots();

        $this->info("Weather snapshots updated: {$updatedSnapshots}.");

        return self::SUCCESS;
    }
}
