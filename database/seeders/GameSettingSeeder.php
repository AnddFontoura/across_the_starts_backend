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
                'value' => '40',
                'type' => 'int',
                'label' => 'Máximo de construções por base',
                'description' => 'Número máximo de estruturas que um jogador pode ter (inclui as em construção).',
            ],
            [
                'key' => 'max_command_per_base',
                'value' => '1',
                'type' => 'int',
                'label' => 'Máximo de Centros de Operações',
                'description' => 'Quantidade máxima de estruturas de comando (Centro de Operações) por base.',
            ],
            [
                'key' => 'max_storage_per_base',
                'value' => '1',
                'type' => 'int',
                'label' => 'Máximo de Depósitos',
                'description' => 'Quantidade máxima de estruturas de armazenamento (Depósito) por base.',
            ],
            [
                'key' => 'max_producers_per_type',
                'value' => '10',
                'type' => 'int',
                'label' => 'Máximo de cada construção de recurso',
                'description' => 'Quantidade máxima de cada tipo de estrutura produtora (ex.: até 10 Minas de Ouro e até 10 Minas de Metal) por base.',
            ],
            [
                'key' => 'max_concurrent_builds',
                'value' => '5',
                'type' => 'int',
                'label' => 'Máximo de obras simultâneas',
                'description' => 'Número máximo de estruturas que podem estar em construção ou evolução ao mesmo tempo.',
            ],

            // --- Planetary (orbital) defense base ---
            [
                'key' => 'defense_center_quantity_cap',
                'value' => '15',
                'type' => 'int',
                'label' => 'Teto de nível do Centro de Defesa para quantidade',
                'description' => 'O Centro de Defesa pode chegar ao nível 30, mas para as regras de quantidade de defesas ele é considerado até este nível.',
            ],
            [
                'key' => 'defense_units_per_center_level',
                'value' => '3',
                'type' => 'int',
                'label' => 'Defesas por nível do Centro de Defesa',
                'description' => 'Quantidade de blocos de defesa, artilharias e canhões de plasma liberados por nível (considerado) do Centro de Defesa.',
            ],
            [
                'key' => 'cosmic_ray_center_levels',
                'value' => '3',
                'type' => 'int',
                'label' => 'Níveis do Centro por Raio Cósmico',
                'description' => 'A cada quantos níveis (considerados) do Centro de Defesa um Raio Cósmico é liberado.',
            ],
        ];

        foreach ($settings as $setting) {
            GameSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
