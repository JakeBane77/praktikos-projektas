<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BuildingTypeSeeder extends Seeder
{
    /**
     * Seed the building types table.
     */
    public function run(): void
    {
        $now = now();

        DB::table('building_types')->upsert(
            [
                [
                    'name' => 'Lumbercamp',
                    'slug' => 'lumbercamp',
                    'produces_resource' => 'wood',
                    'base_production_per_hour' => 10,
                    'production_multiplier' => 1.25,
                    'effect_type' => null,
                    'effects' => null,
                    'base_costs' => json_encode([
                        'wood' => 10,
                        'food' => 5,
                    ]),
                    'upgrade_cost_multiplier' => 1.35,
                    'max_level' => 50,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Farm',
                    'slug' => 'farm',
                    'produces_resource' => 'food',
                    'base_production_per_hour' => 12,
                    'production_multiplier' => 1.25,
                    'effect_type' => null,
                    'effects' => null,
                    'base_costs' => json_encode([
                        'wood' => 20,
                        'food' => 10,
                    ]),
                    'upgrade_cost_multiplier' => 1.35,
                    'max_level' => 50,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Quarry',
                    'slug' => 'quarry',
                    'produces_resource' => 'stone',
                    'base_production_per_hour' => 20,
                    'production_multiplier' => 1.25,
                    'effect_type' => null,
                    'effects' => null,
                    'base_costs' => json_encode([
                        'wood' => 40,
                        'food' => 30,
                    ]),
                    'upgrade_cost_multiplier' => 1.35,
                    'max_level' => 50,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Mine',
                    'slug' => 'mine',
                    'produces_resource' => 'gold',
                    'base_production_per_hour' => 15,
                    'production_multiplier' => 1.15,
                    'effect_type' => null,
                    'effects' => null,
                    'base_costs' => json_encode([
                        'wood' => 50,
                        'food' => 50,
                        'stone' => 80,
                    ]),
                    'upgrade_cost_multiplier' => 1.35,
                    'max_level' => 50,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Road',
                    'slug' => 'road',
                    'produces_resource' => 'road',
                    'base_production_per_hour' => 0,
                    'production_multiplier' => 1,
                    'effect_type' => null,
                    'effects' => null,
                    'base_costs' => json_encode([
                        'food' => 40,
                        'stone' => 50,
                        'gold' => 100,
                    ]),
                    'upgrade_cost_multiplier' => 1,
                    'max_level' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['slug'],
            [
                'name',
                'produces_resource',
                'base_production_per_hour',
                'production_multiplier',
                'effect_type',
                'effects',
                'base_costs',
                'upgrade_cost_multiplier',
                'max_level',
                'updated_at',
            ],
        );
    }
}
