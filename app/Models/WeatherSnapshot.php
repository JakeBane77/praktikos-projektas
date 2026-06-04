<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\WeatherSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property numeric-string $latitude
 * @property numeric-string $longitude
 * @property int $weather_code
 * @property CarbonInterface|null $api_time
 * @property CarbonInterface|null $updated_at
 * @property-read User|null $user
 */
class WeatherSnapshot extends Model
{
    /** @use HasFactory<WeatherSnapshotFactory> */
    use HasFactory;

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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
