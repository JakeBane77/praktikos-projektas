<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property numeric-string $latitude
 * @property numeric-string $longitude
 * @property int $weather_code
 * @property \Carbon\CarbonInterface|null $api_time
 * @property \Carbon\CarbonInterface|null $updated_at
 */
class WeatherSnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'weather_code',
        'api_time',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:4',
            'longitude' => 'decimal:4',
            'api_time' => 'datetime',
        ];
    }
}
