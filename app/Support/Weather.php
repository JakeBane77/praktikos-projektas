<?php

namespace App\Support;

final class Weather
{
    // locations are malaysia (active), los angeles, alytus
    // default location
    public const LATITUDE = 2.495; // 34.0522;//54.6892;//54.3957;

    public const LONGITUDE = 112.4819; // -118.2437;//25.2798;//24.0389;

    public const COORDINATE_PRECISION = 4;

    private const CLEAR_CODES = [0];

    private const CLOUDY_CODES = [1, 2, 3];

    private const RAIN_CODES = [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82];

    private const FOG_CODES = [45, 48];

    private const THUNDERSTORM_CODES = [95, 96, 99];

    private const SNOW_CODES = [71, 73, 75, 77, 85, 86];

    /**
     * @return array{clear: bool, cloudy: bool, raining: bool, foggy: bool, thunderstorm: bool, snowing: bool}
     */
    public static function conditionsFor(?int $weatherCode): array
    {
        return [
            'clear' => $weatherCode !== null && in_array($weatherCode, self::CLEAR_CODES, true),
            'cloudy' => $weatherCode !== null && in_array($weatherCode, self::CLOUDY_CODES, true),
            'raining' => $weatherCode !== null && in_array($weatherCode, self::RAIN_CODES, true),
            'foggy' => $weatherCode !== null && in_array($weatherCode, self::FOG_CODES, true),
            'thunderstorm' => $weatherCode !== null && in_array($weatherCode, self::THUNDERSTORM_CODES, true),
            'snowing' => $weatherCode !== null && in_array($weatherCode, self::SNOW_CODES, true),
        ];
    }

    public static function normalizeCoordinate(float|int|string $coordinate): float
    {
        return round((float) $coordinate, self::COORDINATE_PRECISION);
    }
}
