<?php

namespace Database\Seeders;

use App\Models\GameSetting;
use Illuminate\Database\Seeder;

class GameSettingSeeder extends Seeder
{
    /**
     * Global, editable game configuration.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'default_base_width',
                'value' => '1000',
                'type' => 'int',
                'label' => 'Largura padrão do terreno',
                'description' => 'Largura (em unidades genéricas) das novas bases.',
            ],
            [
                'key' => 'default_base_height',
                'value' => '1000',
                'type' => 'int',
                'label' => 'Altura padrão do terreno',
                'description' => 'Altura (em unidades genéricas) das novas bases.',
            ],
            [
                'key' => 'max_structure_level',
                'value' => '30',
                'type' => 'int',
                'label' => 'Nível máximo de estrutura',
                'description' => 'Teto global de nível para estruturas.',
            ],
            [
                'key' => 'max_structures_per_base',
                'value' => '20',
                'type' => 'int',
                'label' => 'Máximo de construções por base',
                'description' => 'Número máximo de estruturas que um jogador pode ter (inclui as em construção).',
            ],
        ];

        foreach ($settings as $setting) {
            GameSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
