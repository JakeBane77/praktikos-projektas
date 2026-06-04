<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $building_type_id
 * @property int $level
 * @property \Carbon\CarbonInterface|null $built_at
 * @property-read BuildingType|null $buildingType
 * @property-read User|null $user
 */
class UserBuilding extends Model
{
    protected $fillable = [
        'user_id',
        'building_type_id',
        'level',
        'built_at',
    ];

    protected function casts(): array
    {
        return [
            'built_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BuildingType, $this>
     */
    public function buildingType(): BelongsTo
    {
        return $this->belongsTo(BuildingType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
