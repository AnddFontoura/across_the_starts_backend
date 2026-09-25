<?php

namespace Database\Seeders;

use App\Models\ModuleType;
use Illuminate\Database\Seeder;

class ModuleTypeSeeder extends Seeder
{
    /**
     * Example ship modules. Any module may grant any attribute (0 when it
     * doesn't). Weapon modules set attack_type + range. All values are
     * admin-editable.
     */
    public function run(): void
    {
        $modules = [
            // --- Utility / defensive modules (no weapon) ---
            [
                'key' => 'ion_engine',
                'name' => 'Motor Iônico',
                'description' => 'Aumenta o movimento da nave.',
                'color' => '#4fc3f7',
                'movement' => 30,
                'attack' => 0,
                'hull' => 0,
                'shield' => 0,
                'space' => 1,
                'attack_type' => null,
                'range' => 0,
                'build_time_add' => 20,
                'cost_gold' => 40,
                'cost_metal' => 80,
                'cost_energy' => 60,
                'special_attributes' => null,
            ],
            [
                'key' => 'armor_plating',
                'name' => 'Blindagem',
                'description' => 'Reforça a estrutura (hull) da nave.',
                'color' => '#9aa5b1',
                'movement' => 0,
                'attack' => 0,
                'hull' => 500,
                'shield' => 0,
                'space' => 1,
                'attack_type' => null,
                'range' => 0,
                'build_time_add' => 15,
                'cost_gold' => 30,
                'cost_metal' => 120,
                'cost_energy' => 10,
                'special_attributes' => null,
            ],
            [
                'key' => 'shield_generator',
                'name' => 'Gerador de Escudo',
                'description' => 'Adiciona escudo à nave.',
                'color' => '#7fd0ff',
                'movement' => 0,
                'attack' => 0,
                'hull' => 0,
                'shield' => 400,
                'space' => 1,
                'attack_type' => null,
                'range' => 0,
                'build_time_add' => 25,
                'cost_gold' => 50,
                'cost_metal' => 40,
                'cost_energy' => 120,
                'special_attributes' => null,
            ],
            [
                'key' => 'cargo_bay',
                'name' => 'Compartimento de Carga',
                'description' => 'Módulo utilitário leve; ocupa pouco espaço.',
                'color' => '#b07d4f',
                'movement' => 0,
                'attack' => 0,
                'hull' => 100,
                'shield' => 0,
                'space' => 1,
                'attack_type' => null,
                'range' => 0,
                'build_time_add' => 5,
                'cost_gold' => 10,
                'cost_metal' => 20,
                'cost_energy' => 5,
                'special_attributes' => null,
            ],

            // --- Weapon modules (one weapon type per ship) ---
            [
                'key' => 'machinegun',
                'name' => 'Metralhadora',
                'description' => 'Arma de curto alcance e cadência alta.',
                'color' => '#f4c542',
                'movement' => 0,
                'attack' => 80,
                'hull' => 0,
                'shield' => 0,
                'space' => 1,
                'attack_type' => 'machinegun',
                'range' => 2,
                'build_time_add' => 10,
                'cost_gold' => 60,
                'cost_metal' => 90,
                'cost_energy' => 20,
                'special_attributes' => null,
            ],
            [
                'key' => 'laser_cannon',
                'name' => 'Canhão Laser',
                'description' => 'Arma de alcance médio e dano preciso.',
                'color' => '#ff6b6b',
                'movement' => 0,
                'attack' => 140,
                'hull' => 0,
                'shield' => 0,
                'space' => 2,
                'attack_type' => 'laser',
                'range' => 4,
                'build_time_add' => 25,
                'cost_gold' => 100,
                'cost_metal' => 80,
                'cost_energy' => 160,
                'special_attributes' => null,
            ],
            [
                'key' => 'missile_launcher',
                'name' => 'Lançador de Mísseis',
                'description' => 'Arma de longo alcance e alto dano.',
                'color' => '#e05252',
                'movement' => 0,
                'attack' => 220,
                'hull' => 0,
                'shield' => 0,
                'space' => 3,
                'attack_type' => 'missile',
                'range' => 7,
                'build_time_add' => 40,
                'cost_gold' => 150,
                'cost_metal' => 200,
                'cost_energy' => 120,
                'special_attributes' => null,
            ],
        ];

        foreach ($modules as $module) {
            ModuleType::updateOrCreate(['key' => $module['key']], $module);
        }
    }
}
