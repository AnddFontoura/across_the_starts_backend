<?php

namespace Database\Seeders;

use App\Models\StructureType;
use Illuminate\Database\Seeder;

class StructureTypeSeeder extends Seeder
{
    /**
     * Initial structures. Each occupies a 20x20 footprint (generic units).
     *
     * Producers create one resource per hour (up to a capacity ceiling).
     * The Warehouse is a storage structure: it produces nothing but protects
     * an amount of each resource, and its upgrade costs all three resources.
     *
     * Level scaling comes from *_base / *_growth (formula), editable in admin.
     * Formula: value(level) = round(base * growth^(level - 1)).
     */
    public function run(): void
    {
        $types = [
            [
                'key' => 'gold_mine',
                'name' => 'Mina de Ouro',
                'description' => 'Produz ouro a cada hora.',
                'category' => 'producer',
                'width' => 20,
                'height' => 20,
                'resource' => 'gold',
                'production_per_hour' => 100,
                'color' => '#f4c542',
                'max_level' => 30,
                'production_base' => 100,
                'production_growth' => 1.150,
                'capacity_base' => 500,
                'capacity_growth' => 1.200,
                'protection_base' => 0,
                'protection_growth' => 1.000,
                // Costs mostly gold, a little metal.
                'upgrade_cost_gold_base' => 200,
                'upgrade_cost_gold_growth' => 1.500,
                'upgrade_cost_metal_base' => 40,
                'upgrade_cost_metal_growth' => 1.500,
                'upgrade_cost_energy_base' => 0,
                'upgrade_cost_energy_growth' => 1.500,
                // Times in seconds.
                'build_time' => 30,
                'upgrade_time_base' => 60,
                'upgrade_time_growth' => 1.400,
            ],
            [
                'key' => 'metal_mine',
                'name' => 'Mina de Metal',
                'description' => 'Produz metal a cada hora.',
                'category' => 'producer',
                'width' => 20,
                'height' => 20,
                'resource' => 'metal',
                'production_per_hour' => 80,
                'color' => '#9aa5b1',
                'max_level' => 30,
                'production_base' => 80,
                'production_growth' => 1.150,
                'capacity_base' => 400,
                'capacity_growth' => 1.200,
                'protection_base' => 0,
                'protection_growth' => 1.000,
                'upgrade_cost_gold_base' => 60,
                'upgrade_cost_gold_growth' => 1.500,
                'upgrade_cost_metal_base' => 160,
                'upgrade_cost_metal_growth' => 1.500,
                'upgrade_cost_energy_base' => 0,
                'upgrade_cost_energy_growth' => 1.500,
                'build_time' => 30,
                'upgrade_time_base' => 60,
                'upgrade_time_growth' => 1.400,
            ],
            [
                'key' => 'power_plant',
                'name' => 'Gerador de Eletricidade',
                'description' => 'Produz eletricidade a cada hora.',
                'category' => 'producer',
                'width' => 20,
                'height' => 20,
                'resource' => 'energy',
                'production_per_hour' => 60,
                'color' => '#4fc3f7',
                'max_level' => 30,
                'production_base' => 60,
                'production_growth' => 1.150,
                'capacity_base' => 300,
                'capacity_growth' => 1.200,
                'protection_base' => 0,
                'protection_growth' => 1.000,
                'upgrade_cost_gold_base' => 80,
                'upgrade_cost_gold_growth' => 1.500,
                'upgrade_cost_metal_base' => 80,
                'upgrade_cost_metal_growth' => 1.500,
                'upgrade_cost_energy_base' => 40,
                'upgrade_cost_energy_growth' => 1.500,
                'build_time' => 45,
                'upgrade_time_base' => 90,
                'upgrade_time_growth' => 1.400,
            ],
            [
                'key' => 'warehouse',
                'name' => 'Depósito',
                'description' => 'Protege uma quantidade de cada recurso de ser saqueada em ataques.',
                'category' => 'storage',
                'width' => 20,
                'height' => 20,
                'resource' => 'gold', // nominal; storage doesn't produce
                'production_per_hour' => 0,
                'color' => '#b07d4f',
                'max_level' => 30,
                'production_base' => 0,
                'production_growth' => 1.000,
                'capacity_base' => 0,
                'capacity_growth' => 1.000,
                // Protects this much of EACH resource, growing per level.
                'protection_base' => 1000,
                'protection_growth' => 1.250,
                // Upgrade costs all three resources.
                'upgrade_cost_gold_base' => 150,
                'upgrade_cost_gold_growth' => 1.500,
                'upgrade_cost_metal_base' => 150,
                'upgrade_cost_metal_growth' => 1.500,
                'upgrade_cost_energy_base' => 150,
                'upgrade_cost_energy_growth' => 1.500,
                'build_time' => 60,
                'upgrade_time_base' => 120,
                'upgrade_time_growth' => 1.400,
            ],
        ];

        foreach ($types as $type) {
            StructureType::updateOrCreate(['key' => $type['key']], $type);
        }
    }
}
