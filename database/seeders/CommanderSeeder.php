<?php

namespace Database\Seeders;

use App\Models\CommanderDefinition;
use App\Models\RankingBonus;
use Illuminate\Database\Seeder;

class CommanderSeeder extends Seeder
{
    /**
     * The generic recruitable commander plus the ranking-bonus table.
     * Bonus percentages start at 0 and are tuned by the admin.
     */
    public function run(): void
    {
        CommanderDefinition::updateOrCreate(
            ['key' => 'generic'],
            [
                'name' => 'Comandante',
                'description' => 'Comandante padrão recrutado no Hangar de Aeronaves.',
                'color' => '#c9a24b',
                'is_recruitable' => true,
                'max_rank' => 1, // simple commanders are always rank I
            ]
        );

        // Proficiency levels I..V (ship class + weapon). Default 0% (editable).
        for ($level = 1; $level <= 5; $level++) {
            RankingBonus::updateOrCreate(
                ['scope' => RankingBonus::SCOPE_PROFICIENCY, 'level' => $level],
                ['attack_percent' => 0, 'defense_percent' => 0]
            );
        }

        // Commander ranks I..X. Default 0% (effect to be defined later).
        for ($level = 1; $level <= 10; $level++) {
            RankingBonus::updateOrCreate(
                ['scope' => RankingBonus::SCOPE_COMMANDER, 'level' => $level],
                ['attack_percent' => 0, 'defense_percent' => 0]
            );
        }
    }
}
