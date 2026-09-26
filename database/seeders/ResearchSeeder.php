<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ResearchDefinition;
use App\Models\ResearchDefinitionItem;
use Illuminate\Database\Seeder;

/**
 * Seeds the research catalog (technologies + plants) unlocked by the Centro de
 * Pesquisa, plus the plant blueprint / consumable items that plant research
 * requires. Idempotent: everything is keyed by a stable `key` via
 * updateOrCreate so re-running only refreshes definitions.
 *
 * These are sample/placeholder values — the real tech tree and plant list are
 * expected to change (levels, costs and dependencies are all configurable).
 */
class ResearchSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedItems();
        $this->seedTechnologies();
        $this->seedPlants();
    }

    /**
     * Plant blueprints (type 'plant') and one consumable used as an extra
     * research input. Plants are unique (max_stack 1 = a single blueprint).
     */
    protected function seedItems(): void
    {
        $items = [
            [
                'key' => 'blueprint_ion_thruster',
                'name' => 'Planta: Propulsor de Íons',
                'description' => 'Esquema necessário para pesquisar o Propulsor de Íons.',
                'type' => Item::TYPE_PLANT,
                'max_stack' => 1,
                'color' => '#8bd3dd',
            ],
            [
                'key' => 'blueprint_railgun',
                'name' => 'Planta: Railgun',
                'description' => 'Esquema necessário para pesquisar o Railgun.',
                'type' => Item::TYPE_PLANT,
                'max_stack' => 1,
                'color' => '#dd8b8b',
            ],
            [
                'key' => 'rare_alloy',
                'name' => 'Liga Rara',
                'description' => 'Material raro consumido em algumas pesquisas.',
                'type' => Item::TYPE_CONSUMABLE,
                'max_stack' => 9999,
                'color' => '#c9a24b',
            ],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(['key' => $item['key']], $item);
        }
    }

    /**
     * Multi-level technologies (area terrestrial/aerial/weapons) with gold
     * cost, time, per-level effects and dependencies.
     *
     * The first techs are the three resource-collection boosts (max level 10,
     * +5% collection of their resource per level). Once ALL of them reach
     * level 5, the "warehouse protection" tech unlocks: +1,000,000 absolute
     * protection per level, max level 10.
     */
    protected function seedTechnologies(): void
    {
        $A_TERRESTRIAL = ResearchDefinition::AREA_TERRESTRIAL;
        $A_AERIAL = ResearchDefinition::AREA_AERIAL;
        $A_WEAPONS = ResearchDefinition::AREA_WEAPONS;

        $techs = [
            // --- Terrestrial: resource collection boosts (max level 10) ---
            [
                'key' => 'gold_collection',
                'name' => 'Coleta de Ouro',
                'description' => 'Aumenta a coleta de ouro em 5% por nível.',
                'type' => ResearchDefinition::TYPE_TECHNOLOGY,
                'area' => $A_TERRESTRIAL,
                'gold_cost' => 400,
                'gold_cost_growth' => 1.450,
                'research_time' => 240,
                'research_time_growth' => 1.350,
                'max_level' => 10,
                'color' => '#f4c542',
                'effects' => [
                    ['type' => 'resource_production', 'resource' => 'gold', 'percent_per_level' => 5],
                ],
            ],
            [
                'key' => 'metal_collection',
                'name' => 'Coleta de Metal',
                'description' => 'Aumenta a coleta de metal em 5% por nível.',
                'type' => ResearchDefinition::TYPE_TECHNOLOGY,
                'area' => $A_TERRESTRIAL,
                'gold_cost' => 400,
                'gold_cost_growth' => 1.450,
                'research_time' => 240,
                'research_time_growth' => 1.350,
                'max_level' => 10,
                'color' => '#9aa5b1',
                'effects' => [
                    ['type' => 'resource_production', 'resource' => 'metal', 'percent_per_level' => 5],
                ],
            ],
            [
                'key' => 'energy_collection',
                'name' => 'Coleta de Energia',
                'description' => 'Aumenta a coleta de energia em 5% por nível.',
                'type' => ResearchDefinition::TYPE_TECHNOLOGY,
                'area' => $A_TERRESTRIAL,
                'gold_cost' => 400,
                'gold_cost_growth' => 1.450,
                'research_time' => 240,
                'research_time_growth' => 1.350,
                'max_level' => 10,
                'color' => '#5ab0ff',
                'effects' => [
                    ['type' => 'resource_production', 'resource' => 'energy', 'percent_per_level' => 5],
                ],
            ],
            // --- Terrestrial: warehouse protection (unlocked at all collection L5) ---
            [
                'key' => 'warehouse_protection',
                'name' => 'Proteção do Depósito',
                'description' => 'Protege +1.000.000 de cada recurso por nível (máx. 10). Requer todas as pesquisas de coleta no nível 5.',
                'type' => ResearchDefinition::TYPE_TECHNOLOGY,
                'area' => $A_TERRESTRIAL,
                'gold_cost' => 5000,
                'gold_cost_growth' => 1.550,
                'research_time' => 1800,
                'research_time_growth' => 1.400,
                'max_level' => 10,
                'color' => '#4caf7d',
                'effects' => [
                    ['type' => 'warehouse_protection', 'flat_per_level' => 1000000],
                ],
            ],
            // --- Aerial: sample aircraft-class attack boost ---
            [
                'key' => 'cruiser_tactics',
                'name' => 'Táticas de Cruzador',
                'description' => 'Aumenta o ataque dos cruzadores em 4% por nível.',
                'type' => ResearchDefinition::TYPE_TECHNOLOGY,
                'area' => $A_AERIAL,
                'gold_cost' => 1500,
                'gold_cost_growth' => 1.500,
                'research_time' => 900,
                'research_time_growth' => 1.400,
                'max_level' => 10,
                'color' => '#8e7cc3',
                'effects' => [
                    ['type' => 'aircraft_class_attack', 'class' => 'cruiser', 'percent_per_level' => 4],
                ],
            ],
            // --- Weapons: sample laser damage boost ---
            [
                'key' => 'laser_focusing',
                'name' => 'Focalização de Laser',
                'description' => 'Aumenta o dano de armas laser em 3% por nível.',
                'type' => ResearchDefinition::TYPE_TECHNOLOGY,
                'area' => $A_WEAPONS,
                'gold_cost' => 1800,
                'gold_cost_growth' => 1.550,
                'research_time' => 1000,
                'research_time_growth' => 1.400,
                'max_level' => 10,
                'color' => '#ff6b6b',
                'effects' => [
                    ['type' => 'weapon_damage', 'weapon' => 'laser', 'percent_per_level' => 3],
                ],
            ],
        ];

        foreach ($techs as $tech) {
            ResearchDefinition::updateOrCreate(['key' => $tech['key']], $tech);
        }

        // The warehouse protection tech unlocks only when ALL three collection
        // techs reach level 5.
        $protection = ResearchDefinition::where('key', 'warehouse_protection')->first();
        $gold = ResearchDefinition::where('key', 'gold_collection')->first();
        $metal = ResearchDefinition::where('key', 'metal_collection')->first();
        $energy = ResearchDefinition::where('key', 'energy_collection')->first();

        if ($protection && $gold && $metal && $energy) {
            $protection->dependencies()->sync([
                $gold->id => ['min_level' => 5],
                $metal->id => ['min_level' => 5],
                $energy->id => ['min_level' => 5],
            ]);
        }
    }

    /**
     * Single-level plant research. Requires a plant blueprint in the inventory;
     * some also consume extra items when started.
     */
    protected function seedPlants(): void
    {
        $plants = [
            [
                'key' => 'plant_ion_thruster',
                'name' => 'Propulsor de Íons',
                'description' => 'Pesquisa a planta do Propulsor de Íons. Requer o esquema no inventário.',
                'type' => ResearchDefinition::TYPE_PLANT,
                'gold_cost' => 1200,
                'research_time' => 600,
                'max_level' => 1,
                'required_item_key' => 'blueprint_ion_thruster',
                'consumes_required_item' => true,
                'color' => '#8bd3dd',
                // No extra items.
                'extra_items' => [],
            ],
            [
                'key' => 'plant_railgun',
                'name' => 'Railgun',
                'description' => 'Pesquisa a planta do Railgun. Requer o esquema e liga rara no inventário.',
                'type' => ResearchDefinition::TYPE_PLANT,
                'gold_cost' => 2500,
                'research_time' => 900,
                'max_level' => 1,
                'required_item_key' => 'blueprint_railgun',
                'consumes_required_item' => true,
                'color' => '#dd8b8b',
                // Consumes 5 rare alloys in addition to the blueprint.
                'extra_items' => [
                    ['item_key' => 'rare_alloy', 'quantity' => 5],
                ],
            ],
        ];

        foreach ($plants as $plant) {
            $extraItems = $plant['extra_items'];
            unset($plant['extra_items']);

            $def = ResearchDefinition::updateOrCreate(['key' => $plant['key']], $plant);

            // Reset and re-seed the extra consumed items for this definition.
            ResearchDefinitionItem::where('research_definition_id', $def->id)->delete();
            foreach ($extraItems as $req) {
                ResearchDefinitionItem::create([
                    'research_definition_id' => $def->id,
                    'item_key' => $req['item_key'],
                    'quantity' => $req['quantity'],
                ]);
            }
        }
    }
}
