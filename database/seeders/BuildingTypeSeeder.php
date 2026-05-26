<?php

namespace Database\Seeders;

use App\Models\BuildingType;
use Illuminate\Database\Seeder;

class BuildingTypeSeeder extends Seeder
{
    public function run(): void
    {
        $buildingTypes = [
            [
                'name' => 'Mine',
                'slug' => 'mine',
                'produces_resource' => 'gold',
                'base_production_per_hour' => 7,
                'production_multiplier' => 1.20,
                'effect_type' => null,
                'effects' => null,
                'base_costs' => ['wood' => 180, 'stone' => 90],
                'upgrade_cost_multiplier' => 1.25,
            ],
            [
                'name' => 'Sawmill',
                'slug' => 'sawmill',
                'produces_resource' => 'wood',
                'base_production_per_hour' => 6,
                'production_multiplier' => 1.18,
                'effect_type' => null,
                'effects' => null,
                'base_costs' => ['gold' => 140, 'stone' => 60],
                'upgrade_cost_multiplier' => 1.25,
            ],
            [
                'name' => 'Farm',
                'slug' => 'farm',
                'produces_resource' => 'food',
                'base_production_per_hour' => 8,
                'production_multiplier' => 1.15,
                'effect_type' => null,
                'effects' => null,
                'base_costs' => ['wood' => 120, 'gold' => 80],
                'upgrade_cost_multiplier' => 1.22,
            ],
            [
                'name' => 'Quarry',
                'slug' => 'quarry',
                'produces_resource' => 'stone',
                'base_production_per_hour' => 5,
                'production_multiplier' => 1.18,
                'effect_type' => null,
                'effects' => null,
                'base_costs' => ['wood' => 160, 'food' => 70],
                'upgrade_cost_multiplier' => 1.25,
            ],
            [
                'name' => 'Warehouse',
                'slug' => 'warehouse',
                'produces_resource' => null,
                'base_production_per_hour' => 0,
                'production_multiplier' => null,
                'effect_type' => 'storage_bonus',
                'effects' => ['capacity_bonus' => 1000],
                'base_costs' => ['stone' => 220, 'wood' => 160],
                'upgrade_cost_multiplier' => 1.30,
            ],
            [
                'name' => 'Road',
                'slug' => 'road',
                'produces_resource' => null,
                'base_production_per_hour' => 0,
                'production_multiplier' => null,
                'effect_type' => 'road_length',
                'effects' => null,
                'base_costs' => ['wood' => 20, 'stone' => 50],
                'upgrade_cost_multiplier' => 1.10,
            ],
        ];

        foreach ($buildingTypes as $buildingType) {
            BuildingType::updateOrCreate(
                ['slug' => $buildingType['slug']],
                $buildingType,
            );
        }
    }
}
