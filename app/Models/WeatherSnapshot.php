<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
