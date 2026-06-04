<?php

use App\Support\Weather;

test('weather codes are grouped into immersive weather conditions', function (?int $code, array $expected) {
    expect(Weather::conditionsFor($code))->toBe($expected);
})->with([
    'clear' => [0, [
        'clear' => true,
        'cloudy' => false,
        'raining' => false,
        'foggy' => false,
        'thunderstorm' => false,
        'snowing' => false,
    ]],
    'cloudy' => [2, [
        'clear' => false,
        'cloudy' => true,
        'raining' => false,
        'foggy' => false,
        'thunderstorm' => false,
        'snowing' => false,
    ]],
    'rain' => [61, [
        'clear' => false,
        'cloudy' => false,
        'raining' => true,
        'foggy' => false,
        'thunderstorm' => false,
        'snowing' => false,
    ]],
    'fog' => [45, [
        'clear' => false,
        'cloudy' => false,
        'raining' => false,
        'foggy' => true,
        'thunderstorm' => false,
        'snowing' => false,
    ]],
    'thunderstorm' => [95, [
        'clear' => false,
        'cloudy' => false,
        'raining' => false,
        'foggy' => false,
        'thunderstorm' => true,
        'snowing' => false,
    ]],
    'snow' => [71, [
        'clear' => false,
        'cloudy' => false,
        'raining' => false,
        'foggy' => false,
        'thunderstorm' => false,
        'snowing' => true,
    ]],
    'unknown' => [null, [
        'clear' => false,
        'cloudy' => false,
        'raining' => false,
        'foggy' => false,
        'thunderstorm' => false,
        'snowing' => false,
    ]],
]);

test('weather coordinates are normalized to the configured precision', function () {
    expect(Weather::normalizeCoordinate('54.39576'))->toBe(54.3958)
        ->and(Weather::normalizeCoordinate(24.03894))->toBe(24.0389)
        ->and(Weather::normalizeCoordinate(-118.24375))->toBe(-118.2438);
});
