<?php

namespace App\Models;

use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $building_type_id
 * @property int|null $bonus_building_type_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $type
 * @property string|null $resource_type
 * @property int $target_value
 * @property int $reward_gold
 * @property int $reward_wood
 * @property int $reward_stone
 * @property int $reward_food
 * @property int $production_bonus_percent
 * @property-read BuildingType|null $buildingType
 * @property-read BuildingType|null $bonusBuildingType
 */
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    use HasFactory;

    protected $fillable = [
        'building_type_id',
        'bonus_building_type_id',
        'name',
        'slug',
        'description',
        'type',
        'resource_type',
        'target_value',
        'reward_gold',
        'reward_wood',
        'reward_stone',
        'reward_food',
        'production_bonus_percent',
    ];

    /**
     * @return BelongsTo<BuildingType, $this>
     */
    public function buildingType(): BelongsTo
    {
        return $this->belongsTo(BuildingType::class);
    }

    /**
     * @return BelongsTo<BuildingType, $this>
     */
    public function bonusBuildingType(): BelongsTo
    {
        return $this->belongsTo(BuildingType::class, 'bonus_building_type_id');
    }

    /**
     * @return HasMany<UserAchievement, $this>
     */
    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
