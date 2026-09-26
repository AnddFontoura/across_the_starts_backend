<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Catalog items. For now just the "Pergaminho do Caminho", a consumable
     * that re-rolls a commander's growth factors (the per-attribute factors
     * decided at recruitment).
     */
    public function run(): void
    {
        Item::updateOrCreate(
            ['key' => 'pergaminho_do_caminho'],
            [
                'name' => 'Pergaminho do Caminho',
                'description' => 'Re-rola os fatores de crescimento de um comandante, redistribuindo o potencial de evolução de seus atributos.',
                'type' => Item::TYPE_CONSUMABLE,
                'effects' => ['reroll_commander_growth' => true],
                'max_stack' => 9999,
                'color' => '#7c5cff',
            ]
        );
    }
}
