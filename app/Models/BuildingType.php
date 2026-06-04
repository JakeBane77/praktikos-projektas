<?php

namespace App\Models;

use Database\Factories\BuildingTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $produces_resource
 * @property numeric-string|float|int $base_production_per_hour
 * @property numeric-string|float|int $production_multiplier
 * @property string|null $effect_type
 * @property array<string, mixed>|null $effects
 * @property array<string, int>|null $base_costs
 * @property numeric-string|float|int $upgrade_cost_multiplier
 * @property int|null $max_level
 */
class BuildingType extends Model
{
    /** @use HasFactory<BuildingTypeFactory> */
    use HasFactory;

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

    /**
     * @return HasMany<UserBuilding, $this>
     */
    public function userBuildings(): HasMany
    {
        return $this->hasMany(UserBuilding::class);
    }
}
