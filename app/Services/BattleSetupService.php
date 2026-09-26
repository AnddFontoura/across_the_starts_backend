<?php

namespace App\Services;

use App\Models\AircraftType;
use App\Models\BattleFleet;
use App\Models\BattleInstance;
use App\Models\BattleShip;
use App\Models\Commander;
use App\Models\Fleet;
use App\Models\InvestigationDefinition;
use App\Models\InvestigationEnemyFleet;
use App\Models\ShipDesign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Builds a live BattleInstance from an investigation definition and the set of
 * player fleets the player chose to dispatch.
 *
 * On start it:
 *  - validates the chosen fleets (ownership, count within the definition's
 *    min/max, and that no fleet is already committed to another battle);
 *  - snapshots each side's ships into battle_ships with per-ship combat stats
 *    (player stats via FleetCompositionService/ShipDesignService including all
 *    commander & research bonuses; enemy stats via ShipDesignService from the
 *    admin-configured aircraft_type + module loadout);
 *  - places fleets at their predefined start positions on the isolated map.
 *
 * Dispatched fleets are "locked" simply by having a battle_fleet row on an
 * in-progress instance (see Fleet::isInBattle); FleetController excludes them
 * from the base and blocks edits.
 */
class BattleSetupService
{
    /** Fallback per-fleet start spacing on the map (in 10-unit cells). */
    protected const PLAYER_START_X = 0;
    protected const ENEMY_START_X_FALLBACK = 100;

    public function __construct(
        protected FleetCompositionService $composition,
        protected ShipDesignService $designs,
    ) {
    }

    /**
     * Create and populate a battle instance.
     *
     * @param  array<int, int>  $fleetIds  the player fleets to dispatch
     */
    public function start(User $user, InvestigationDefinition $definition, array $fleetIds): BattleInstance
    {
        if (! $definition->is_active) {
            throw ValidationException::withMessages([
                'investigation' => ['Esta investigação não está disponível.'],
            ]);
        }

        // No concurrent runs of any investigation for the same player.
        $existing = BattleInstance::where('user_id', $user->id)
            ->where('status', BattleInstance::STATUS_IN_PROGRESS)
            ->exists();
        if ($existing) {
            throw ValidationException::withMessages([
                'investigation' => ['Você já tem uma investigação em andamento. Volte a ela para continuar.'],
            ]);
        }

        $fleetIds = array_values(array_unique(array_map('intval', $fleetIds)));

        $count = count($fleetIds);
        if ($count < $definition->min_player_fleets || $count > $definition->max_player_fleets) {
            throw ValidationException::withMessages([
                'fleets' => [
                    "Esta investigação exige entre {$definition->min_player_fleets} e {$definition->max_player_fleets} frota(s).",
                ],
            ]);
        }

        $fleets = Fleet::where('user_id', $user->id)
            ->whereIn('id', $fleetIds)
            ->with(['commander', 'slots.design.baseType', 'slots.design.modules.moduleType'])
            ->get();

        if ($fleets->count() !== $count) {
            throw ValidationException::withMessages([
                'fleets' => ['Uma ou mais frotas selecionadas não pertencem a você.'],
            ]);
        }

        foreach ($fleets as $fleet) {
            if ($fleet->isInBattle()) {
                throw ValidationException::withMessages([
                    'fleets' => ["A frota \"{$fleet->name}\" já está em uma batalha."],
                ]);
            }
        }

        $enemyFleets = $definition->enemyFleets()->with('slots.baseType')->get();
        if ($enemyFleets->isEmpty()) {
            throw ValidationException::withMessages([
                'investigation' => ['Esta investigação não tem frotas inimigas configuradas.'],
            ]);
        }

        return DB::transaction(function () use ($user, $definition, $fleets, $enemyFleets) {
            $instance = BattleInstance::create([
                'user_id' => $user->id,
                'investigation_definition_id' => $definition->id,
                'status' => BattleInstance::STATUS_IN_PROGRESS,
                'current_round' => 0,
                'max_rounds' => $definition->max_rounds,
                'map_width' => $definition->map_width,
                'map_height' => $definition->map_height,
                'seed' => random_int(1, PHP_INT_MAX),
                'settled' => false,
                'rewards_claimed' => false,
            ]);

            $this->buildPlayerFleets($instance, $user, $fleets);
            $this->buildEnemyFleets($instance, $enemyFleets);

            return $instance->fresh();
        });
    }

    /**
     * Snapshot each player fleet into a battle_fleet + battle_ships. Per-ship
     * stats come from FleetCompositionService (bonuses folded in): we compute
     * the fleet summary once for its movement/commander percentages, then per
     * design stats via a single-slot summary so each stack gets its own numbers.
     */
    protected function buildPlayerFleets(BattleInstance $instance, User $user, $fleets): void
    {
        $index = 0;
        foreach ($fleets as $fleet) {
            /** @var Commander|null $commander */
            $commander = $fleet->commander;

            $slots = $fleet->slots->map(fn ($s) => [
                'ship_design_id' => $s->ship_design_id,
                'quantity' => (int) $s->quantity,
            ])->all();

            $summary = $this->composition->summarize($slots, $commander, $user);

            $velocidade = $commander ? (int) round($commander->attributeValue('velocidade')) : 0;

            $start = $this->playerStart($instance, $index);

            // Energy: the fleet enters battle with whatever it was fueled to.
            // Store the PER-SHIP upkeep so the round drain scales down as ships
            // are lost (see BattleService::energyCost).
            $totalShips = max(1, (int) $summary['ships']);
            $perShipUpkeep = (int) round((int) $summary['energy_upkeep'] / $totalShips);

            $battleFleet = BattleFleet::create([
                'battle_instance_id' => $instance->id,
                'side' => BattleInstance::SIDE_PLAYER,
                'fleet_id' => $fleet->id,
                'commander_id' => $fleet->commander_id,
                'investigation_enemy_fleet_id' => null,
                'name' => $fleet->name,
                'velocidade' => $velocidade,
                'attack_percent' => 0, // player bonuses already folded per-ship
                'defense_percent' => 0,
                'energy' => (int) $fleet->energy,
                'energy_upkeep' => $perShipUpkeep,
                'x' => $start['x'],
                'y' => $start['y'],
                'movement' => max(1, (int) $summary['movement']),
                'alive' => true,
            ]);

            foreach ($fleet->slots as $slot) {
                $design = $slot->design;
                // Per-ship stats WITH bonuses: summarize just this design's stack.
                $per = $this->composition->summarize(
                    [['ship_design_id' => $slot->ship_design_id, 'quantity' => 1]],
                    $commander,
                    $user,
                );

                $this->createShipStack($battleFleet, [
                    'ship_design_id' => $slot->ship_design_id,
                    'aircraft_type_id' => $design->aircraft_type_id,
                    'name' => $design->name,
                    'ship_class' => $design->baseType->class,
                    'weapon_type' => $this->designWeaponType($design),
                    'attack' => (int) $per['attack'],
                    'hull' => (int) $per['hull'],
                    'shield' => (int) $per['shield'],
                    'weapon_range' => $this->designWeaponRange($design),
                    'movement' => (int) $per['movement'],
                    'quantity' => (int) $slot->quantity,
                ]);
            }
        }
    }

    /**
     * Snapshot each admin-configured enemy fleet into a battle_fleet +
     * battle_ships. Enemy per-ship stats come from ShipDesignService::summarize
     * over the aircraft_type + module loadout, then boosted by the enemy
     * fleet's flat attack/defense percentages.
     */
    protected function buildEnemyFleets(BattleInstance $instance, $enemyFleets): void
    {
        foreach ($enemyFleets as $enemy) {
            /** @var InvestigationEnemyFleet $enemy */
            $movement = null;

            $battleFleet = BattleFleet::create([
                'battle_instance_id' => $instance->id,
                'side' => BattleInstance::SIDE_ENEMY,
                'fleet_id' => null,
                'commander_id' => null,
                'investigation_enemy_fleet_id' => $enemy->id,
                'name' => $enemy->name,
                'velocidade' => (int) $enemy->commander_velocidade,
                'attack_percent' => (int) $enemy->attack_percent,
                'defense_percent' => (int) $enemy->defense_percent,
                'x' => (int) $enemy->start_x,
                'y' => (int) $enemy->start_y,
                'movement' => 1, // set below once ships are known
                'alive' => true,
            ]);

            foreach ($enemy->slots as $slot) {
                $base = $slot->baseType;
                $modules = $this->designs->resolveModules($slot->modules ?? []);
                $per = $this->designs->summarize($base, $modules);

                $atk = (int) round($per['attack'] * (100 + $enemy->attack_percent) / 100);
                $hull = (int) round($per['hull'] * (100 + $enemy->defense_percent) / 100);
                $shield = (int) round($per['shield'] * (100 + $enemy->defense_percent) / 100);

                $this->createShipStack($battleFleet, [
                    'ship_design_id' => null,
                    'aircraft_type_id' => $base->id,
                    'name' => $slot->name ?: $base->name,
                    'ship_class' => $base->class,
                    'weapon_type' => $per['weapon_type'],
                    'attack' => $atk,
                    'hull' => max(1, $hull),
                    'shield' => $shield,
                    'weapon_range' => (int) $per['weapon_range'],
                    'movement' => (int) $per['movement'],
                    'quantity' => (int) $slot->quantity,
                ]);

                $movement = $movement === null
                    ? (int) $per['movement']
                    : min($movement, (int) $per['movement']);
            }

            $battleFleet->movement = max(1, (int) ($movement ?? 1));
            $battleFleet->save();
        }
    }

    /**
     * Create a battle_ships row from a per-ship stat array + a total quantity.
     * Pools the stack's hp so damage removes whole ships as it accumulates.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createShipStack(BattleFleet $fleet, array $data): BattleShip
    {
        $perShipHp = max(1, (int) $data['shield'] + (int) $data['hull']);
        $qty = (int) $data['quantity'];

        return BattleShip::create([
            'battle_fleet_id' => $fleet->id,
            'ship_design_id' => $data['ship_design_id'],
            'aircraft_type_id' => $data['aircraft_type_id'],
            'name' => $data['name'],
            'ship_class' => $data['ship_class'],
            'weapon_type' => $data['weapon_type'],
            'attack' => (int) $data['attack'],
            'hull' => (int) $data['hull'],
            'shield' => (int) $data['shield'],
            'weapon_range' => (int) $data['weapon_range'],
            'movement' => (int) $data['movement'],
            'quantity' => $qty,
            'quantity_remaining' => $qty,
            'hp_remaining' => $perShipHp * $qty,
        ]);
    }

    /** The weapon type of a player design (max range weapon), or null. */
    protected function designWeaponType(ShipDesign $design): ?string
    {
        $summary = $this->designs->summarize($design->baseType, $design->modules->map(fn ($dm) => [
            'module' => $dm->moduleType,
            'quantity' => (int) $dm->quantity,
        ])->all());

        return $summary['weapon_type'];
    }

    protected function designWeaponRange(ShipDesign $design): int
    {
        $summary = $this->designs->summarize($design->baseType, $design->modules->map(fn ($dm) => [
            'module' => $dm->moduleType,
            'quantity' => (int) $dm->quantity,
        ])->all());

        return (int) $summary['weapon_range'];
    }

    /**
     * A player fleet's start position: stacked down the left edge of the map so
     * fleets don't overlap, one every SIZE+ gap.
     */
    protected function playerStart(BattleInstance $instance, int $index): array
    {
        $gap = Fleet::SIZE + 2;
        $y = min($index * $gap, max(0, (int) $instance->map_height - Fleet::SIZE));

        return ['x' => self::PLAYER_START_X, 'y' => $y];
    }
}
