<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
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

    public function buildingType(): BelongsTo
    {
        return $this->belongsTo(BuildingType::class);
    }

    public function bonusBuildingType(): BelongsTo
    {
        return $this->belongsTo(BuildingType::class, 'bonus_building_type_id');
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
