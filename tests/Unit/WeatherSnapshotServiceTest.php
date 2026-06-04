<?php

use App\Models\User;
use App\Models\WeatherSnapshot;
use App\Services\WeatherSnapshotService;
use App\Support\Weather;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('weather snapshot service stores normalized default location weather from open meteo', function () {
    config(['app.timezone' => 'Europe/Vilnius']);

    Http::fake([
        'api.open-meteo.com/*' => Http::response([
            'timezone' => 'Europe/London',
            'current' => [
                'time' => '2026-06-04T08:15',
                'weather_code' => 63,
            ],
        ]),
    ]);

    $snapshot = app(WeatherSnapshotService::class)->update(54.39576, 24.03894);

    expect((float) $snapshot->latitude)->toBe(54.3958)
        ->and((float) $snapshot->longitude)->toBe(24.0389)
        ->and($snapshot->weather_code)->toBe(63)
        ->and($snapshot->api_time?->format('Y-m-d H:i:s'))->toBe('2026-06-04 10:15:00');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.open-meteo.com/v1/forecast')
        && (float) $request['latitude'] === 54.3958
        && (float) $request['longitude'] === 24.0389
        && $request['current'] === 'weather_code'
        && $request['timezone'] === 'auto');
});

test('weather snapshot service updates one personal snapshot per user', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::sequence()
            ->push([
                'timezone' => 'UTC',
                'current' => [
                    'time' => '2026-06-04T09:00',
                    'weather_code' => 1,
                ],
            ])
            ->push([
                'timezone' => 'UTC',
                'current' => [
                    'time' => '2026-06-04T09:15',
                    'weather_code' => 71,
                ],
            ]),
    ]);

    $user = User::factory()->create();
    $service = app(WeatherSnapshotService::class);

    $firstSnapshot = $service->update(10, 20, $user->id);
    $secondSnapshot = $service->update(11, 21, $user->id);

    expect($secondSnapshot->id)->toBe($firstSnapshot->id)
        ->and(WeatherSnapshot::where('user_id', $user->id)->count())->toBe(1)
        ->and((float) $secondSnapshot->latitude)->toBe(11.0)
        ->and((float) $secondSnapshot->longitude)->toBe(21.0)
        ->and($secondSnapshot->weather_code)->toBe(71);
});

test('weather snapshot service stores null weather fields when open meteo omits current weather', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response([
            'timezone' => 'UTC',
        ]),
    ]);

    $snapshot = app(WeatherSnapshotService::class)->update(Weather::LATITUDE, Weather::LONGITUDE);

    expect($snapshot->weather_code)->toBeNull()
        ->and($snapshot->api_time)->toBeNull();
});

test('weather snapshot service does not overwrite existing snapshots when open meteo fails', function () {
    $snapshot = WeatherSnapshot::create([
        'latitude' => Weather::LATITUDE,
        'longitude' => Weather::LONGITUDE,
        'weather_code' => 0,
        'api_time' => '2026-06-04 09:00:00',
    ]);

    Http::fake([
        'api.open-meteo.com/*' => Http::response(['reason' => 'unavailable'], 500),
    ]);

    expect(fn () => app(WeatherSnapshotService::class)->update(Weather::LATITUDE, Weather::LONGITUDE))
        ->toThrow(RequestException::class);

    $snapshot->refresh();

    expect($snapshot->weather_code)->toBe(0)
        ->and($snapshot->api_time?->format('Y-m-d H:i:s'))->toBe('2026-06-04 09:00:00');
});
