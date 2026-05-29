<?php

namespace App\Support;

final class Weather
{
    public const LATITUDE = 34.0522;//54.6892;//54.3957;
    
    public const LONGITUDE = -118.2437;//25.2798;//24.0389;

    public const COORDINATE_PRECISION = 4;

    private const SUNNY_CODES = [0, 1, 2];

    private const RAIN_CODES = [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82];

    private const FOG_CODES = [45, 48];

    private const THUNDERSTORM_CODES = [95, 96, 99];

    private const SNOW_CODES = [71, 73, 75, 77, 85, 86];

    /**
     * @return array{sunny: bool, raining: bool, foggy: bool, thunderstorm: bool, snowing: bool}
     */
    public static function conditionsFor(?int $weatherCode): array
    {
        return [
            'sunny' => $weatherCode !== null && in_array($weatherCode, self::SUNNY_CODES, true),
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
