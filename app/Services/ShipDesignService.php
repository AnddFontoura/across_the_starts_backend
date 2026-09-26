<?php

namespace App\Services;

use App\Models\AircraftType;
use App\Models\ModuleType;
use App\Models\ShipDesign;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validates and summarizes custom ship designs. A design is a base aircraft
 * type filled with modules:
 *  - at least 1 module,
 *  - total occupied space <= the base type's storage,
 *  - at most ONE weapon type (machinegun/laser/missile) across all modules.
 * Final stats = base ship stats + summed module contributions. Total cost =
 * base cost + module costs. Total build time = base build time + summed
 * module build_time_add.
 */
class ShipDesignService
{
    /**
     * Validate a set of modules against a base aircraft type. $modules is a
     * list of ['module' => ModuleType, 'quantity' => int].
     *
     * @param  array<int, array{module: ModuleType, quantity: int}>  $modules
     *
     * @throws ValidationException
     */
    public function validate(AircraftType $base, array $modules): void
    {
        // At least one module.
        $totalUnits = array_sum(array_map(fn ($m) => (int) $m['quantity'], $modules));
        if ($totalUnits < 1) {
            throw ValidationException::withMessages([
                'modules' => ['A nave precisa de pelo menos 1 módulo.'],
            ]);
        }

        // Space must fit within the base ship's storage.
        $usedSpace = 0;
        foreach ($modules as $m) {
            $usedSpace += (int) $m['module']->space * (int) $m['quantity'];
        }
        if ($usedSpace > (int) $base->storage) {
            throw ValidationException::withMessages([
                'space' => ["Espaço insuficiente: usa {$usedSpace}, disponível {$base->storage}."],
            ]);
        }

        // At most one weapon type.
        $weaponTypes = [];
        foreach ($modules as $m) {
            if ($m['module']->isWeapon()) {
                $weaponTypes[$m['module']->attack_type] = true;
            }
        }
        if (count($weaponTypes) > 1) {
            throw ValidationException::withMessages([
                'weapon' => ['Uma nave só pode usar um tipo de armamento (metralhadora, laser OU míssil).'],
            ]);
        }
    }

    /**
     * Aggregate stats + cost + build time for a set of modules on a base type.
     *
     * @param  array<int, array{module: ModuleType, quantity: int}>  $modules
     */
    public function summarize(AircraftType $base, array $modules): array
    {
        $movement = (int) $base->movement;
        $shield = (int) $base->shield;
        $hull = (int) $base->hull;
        $energyCapacity = (int) $base->energy_capacity;
        $energyUpkeep = (int) $base->energy_upkeep;
        $usedSpace = 0;
        $buildTime = (int) $base->build_time;

        $cost = $base->cost();
        $attackByType = ['machinegun' => 0, 'laser' => 0, 'missile' => 0];
        $weaponType = null;
        $weaponRange = 0;

        foreach ($modules as $m) {
            $mod = $m['module'];
            $q = (int) $m['quantity'];

            $movement += (int) $mod->movement * $q;
            $shield += (int) $mod->shield * $q;
            $hull += (int) $mod->hull * $q;
            $energyCapacity += (int) $mod->energy_capacity * $q;
            $energyUpkeep += (int) $mod->energy_upkeep * $q;
            $usedSpace += (int) $mod->space * $q;
            $buildTime += (int) $mod->build_time_add * $q;

            $cost['gold'] += (int) $mod->cost_gold * $q;
            $cost['metal'] += (int) $mod->cost_metal * $q;
            $cost['energy'] += (int) $mod->cost_energy * $q;

            if ($mod->isWeapon()) {
                $attackByType[$mod->attack_type] += (int) $mod->attack * $q;
                $weaponType = $mod->attack_type;
                $weaponRange = max($weaponRange, (int) $mod->range);
            }
        }

        return [
            'movement' => $movement,
            'shield' => $shield,
            'hull' => $hull,
            'used_space' => $usedSpace,
            'total_space' => (int) $base->storage,
            'free_space' => max(0, (int) $base->storage - $usedSpace),
            'weapon_type' => $weaponType,
            'weapon_range' => $weaponRange,
            'attack' => $weaponType ? $attackByType[$weaponType] : 0,
            // Energy tank this single ship contributes (base + modules) and the
            // energy one ship spends per combat action (attack or defense).
            'energy_capacity' => $energyCapacity,
            'energy_upkeep' => $energyUpkeep,
            'cost' => $cost,
            'build_time' => $buildTime,
        ];
    }

    /**
     * Resolve a list of {module_type_id, quantity} inputs into ModuleType
     * instances, rejecting unknown ids and non-positive quantities.
     *
     * @param  array<int, array{module_type_id:int, quantity:int}>  $input
     * @return array<int, array{module: ModuleType, quantity: int}>
     */
    public function resolveModules(array $input): array
    {
        $ids = array_map(fn ($i) => (int) $i['module_type_id'], $input);
        $types = ModuleType::whereIn('id', $ids)->get()->keyBy('id');

        $resolved = [];
        foreach ($input as $i) {
            $id = (int) $i['module_type_id'];
            $qty = (int) $i['quantity'];
            if ($qty < 1) {
                continue;
            }
            $type = $types->get($id);
            if (! $type) {
                throw ValidationException::withMessages([
                    'modules' => ["Módulo inválido: {$id}."],
                ]);
            }
            $resolved[] = ['module' => $type, 'quantity' => $qty];
        }

        return $resolved;
    }

    /**
     * Serialize a saved design (with its modules loaded) into a summary array.
     */
    public function serialize(ShipDesign $design): array
    {
        $design->loadMissing(['baseType', 'modules.moduleType']);

        $modules = $design->modules->map(fn ($dm) => [
            'module' => $dm->moduleType,
            'quantity' => (int) $dm->quantity,
        ])->all();

        $summary = $this->summarize($design->baseType, $modules);

        return [
            'id' => $design->id,
            'name' => $design->name,
            'aircraft_type_id' => $design->aircraft_type_id,
            'base_type' => [
                'id' => $design->baseType->id,
                'key' => $design->baseType->key,
                'class' => $design->baseType->class,
                'name' => $design->baseType->name,
                'color' => $design->baseType->color,
                'image_url' => $design->baseType->image_url,
                'storage' => $design->baseType->storage,
            ],
            'modules' => $design->modules->map(fn ($dm) => [
                'module_type_id' => $dm->module_type_id,
                'quantity' => (int) $dm->quantity,
                'name' => $dm->moduleType->name,
                'attack_type' => $dm->moduleType->attack_type,
                'image_url' => $dm->moduleType->image_url,
            ])->values(),
            'summary' => $summary,
        ];
    }
}
