<?php

namespace Database\Seeders;

use App\Models\AircraftMatchup;
use App\Models\AircraftType;
use Illuminate\Database\Seeder;

class AircraftTypeSeeder extends Seeder
{
    /**
     * Seed the four aircraft types and their counter matrix. Aircraft are flat
     * units (no levels) and deal no innate damage — damage comes from modules
     * (a future feature). The matchup matrix (bonus/reduction %) is fully
     * admin-editable.
     */
    public function run(): void
    {
        $types = [
            [
                'key' => 'cruiser',
                'class' => 'cruiser',
                'name' => 'Cruzador',
                'description' => 'Nave versátil de porte médio, equilibrada entre proteção e mobilidade.',
                'color' => '#5ab0ff',
                'storage' => 6,
                'cost_gold' => 400,
                'cost_metal' => 600,
                'cost_energy' => 200,
                'build_time' => 300,
                'shield' => 1200,
                'hull' => 1800,
                'movement' => 40,
                'special_attributes' => null,
            ],
            [
                'key' => 'battleship',
                'class' => 'battleship',
                'name' => 'Encouraçado',
                'description' => 'Nave pesada de linha de frente, muita estrutura e escudo, porém lenta.',
                'color' => '#2f6fd6',
                'storage' => 10,
                'cost_gold' => 900,
                'cost_metal' => 1500,
                'cost_energy' => 500,
                'build_time' => 720,
                'shield' => 3000,
                'hull' => 5000,
                'movement' => 20,
                'special_attributes' => null,
            ],
            [
                'key' => 'frigate',
                'class' => 'frigate',
                'name' => 'Fragata',
                'description' => 'Nave de escolta ágil, boa contra alvos maiores.',
                'color' => '#7fd0ff',
                'storage' => 4,
                'cost_gold' => 250,
                'cost_metal' => 350,
                'cost_energy' => 150,
                'build_time' => 180,
                'shield' => 700,
                'hull' => 1000,
                'movement' => 60,
                'special_attributes' => null,
            ],
            [
                'key' => 'fighter',
                'class' => 'fighter',
                'name' => 'Caça',
                'description' => 'Nave leve e muito rápida, opera em enxames.',
                'color' => '#9fe0ff',
                'storage' => 2,
                'cost_gold' => 80,
                'cost_metal' => 120,
                'cost_energy' => 60,
                'build_time' => 60,
                'shield' => 200,
                'hull' => 300,
                'movement' => 100,
                'special_attributes' => null,
            ],
        ];

        $byKey = [];
        foreach ($types as $type) {
            $byKey[$type['key']] = AircraftType::updateOrCreate(['key' => $type['key']], $type);
        }

        // Counter matrix. attacker => [defender => [bonus%, reduction%]].
        // A rock-paper-scissors style triangle plus swarm/anti-swarm dynamics.
        // bonus = extra % damage attacker deals to defender.
        // reduction = % damage defender mitigates from attacker.
        $matrix = [
            'cruiser' => [
                'cruiser' => [0, 0],
                'battleship' => [20, 0],   // cruisers punch up vs battleships
                'frigate' => [0, 10],
                'fighter' => [10, 0],
            ],
            'battleship' => [
                'cruiser' => [15, 0],
                'battleship' => [0, 0],
                'frigate' => [25, 0],      // battleships crush frigates
                'fighter' => [0, 20],      // but shrug off fighters
            ],
            'frigate' => [
                'cruiser' => [20, 0],      // frigates counter cruisers
                'battleship' => [10, 0],
                'frigate' => [0, 0],
                'fighter' => [0, 10],
            ],
            'fighter' => [
                'cruiser' => [0, 0],
                'battleship' => [30, 0],   // fighter swarms tear battleships
                'frigate' => [15, 0],
                'fighter' => [0, 0],
            ],
        ];

        // The matrix is keyed by CLASS (not individual ship).
        foreach ($matrix as $attackerClass => $defenders) {
            foreach ($defenders as $defenderClass => [$bonus, $reduction]) {
                AircraftMatchup::updateOrCreate(
                    [
                        'attacker_class' => $attackerClass,
                        'defender_class' => $defenderClass,
                    ],
                    [
                        'damage_bonus_percent' => $bonus,
                        'damage_reduction_percent' => $reduction,
                    ]
                );
            }
        }
    }
}
