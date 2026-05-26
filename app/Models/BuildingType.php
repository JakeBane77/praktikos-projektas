<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'produces_resource',
        'base_production_per_hour',
        'production_multiplier',
        'effect_type',
        'effects',
        'base_costs',
        'upgrade_cost_multiplier',
        'max_level',
    ];

    protected function casts(): array
    {
        return [
            'base_costs' => 'array',
            'effects' => 'array',
            'production_multiplier' => 'decimal:2',
            'upgrade_cost_multiplier' => 'decimal:2',
        ];
    }

    public function userBuildings(): HasMany
    {
        return $this->hasMany(UserBuilding::class);
    }
}
