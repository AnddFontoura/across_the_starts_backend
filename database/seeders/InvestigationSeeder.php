<?php

namespace Database\Seeders;

use App\Models\AircraftType;
use App\Models\InvestigationDefinition;
use App\Models\InvestigationEnemyFleet;
use App\Models\InvestigationEnemySlot;
use App\Models\InvestigationPrize;
use App\Models\Item;
use App\Models\ModuleType;
use Illuminate\Database\Seeder;

/**
 * A sample "Investigação Interplanetária" so the feature is playable out of the
 * box. References aircraft types and modules by key (resolved to ids at seed
 * time) so it stays robust to id changes. Also seeds a placeholder "random
 * resource box" prize item (its contents are resolved on use, defined later).
 */
class InvestigationSeeder extends Seeder
{
    public function run(): void
    {
        // Placeholder prize item: a random resource box (behaviour defined later).
        $box = Item::updateOrCreate(
            ['key' => 'caixa_recursos_aleatoria'],
            [
                'name' => 'Caixa de Recursos Aleatória',
                'description' => 'Abre para receber uma quantidade aleatória de recursos.',
                'type' => Item::TYPE_CONSUMABLE,
                'effects' => ['random_resource_box' => true],
                'max_stack' => 9999,
                'color' => '#f4c542',
            ]
        );

        $enemyType = AircraftType::query()->orderBy('id')->first();
        if (! $enemyType) {
            // No aircraft types seeded yet; skip (seeder order should prevent this).
            return;
        }

        $weapon = ModuleType::where('key', 'machinegun')->first()
            ?? ModuleType::whereNotNull('attack_type')->first();

        $definition = InvestigationDefinition::updateOrCreate(
            ['key' => 'ruinas_orbitais'],
            [
                'name' => 'Ruínas Orbitais',
                'description' => 'Uma investigação introdutória contra uma frota abandonada em órbita.',
                'min_player_fleets' => 1,
                'max_player_fleets' => 2,
                'map_width' => 200,
                'map_height' => 200,
                'max_rounds' => 30,
                'exp_reward' => 150,
                'is_active' => true,
                'color' => '#7ec8e3',
            ]
        );

        // Rebuild the enemy fleet(s) idempotently.
        $definition->enemyFleets()->delete();

        $enemy = InvestigationEnemyFleet::create([
            'investigation_definition_id' => $definition->id,
            'name' => 'Sentinelas Abandonadas',
            'start_x' => 180,
            'start_y' => 90,
            'commander_velocidade' => 8,
            'attack_percent' => 0,
            'defense_percent' => 0,
        ]);

        InvestigationEnemySlot::create([
            'investigation_enemy_fleet_id' => $enemy->id,
            'aircraft_type_id' => $enemyType->id,
            'name' => 'Sentinela',
            'modules' => $weapon ? [['module_type_id' => $weapon->id, 'quantity' => 2]] : [],
            'quantity' => 8,
        ]);

        // Prize: the random resource box (guaranteed).
        $definition->prizes()->delete();
        InvestigationPrize::create([
            'investigation_definition_id' => $definition->id,
            'item_id' => $box->id,
            'quantity' => 1,
            'chance' => 100,
        ]);
    }
}
