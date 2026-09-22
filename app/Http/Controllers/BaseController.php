<?php

namespace App\Http\Controllers;

use App\Models\Base;
use App\Models\StructureType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    /**
     * Return the player's base: dimensions, resource balances, placed
     * structures and the catalog of buildable structure types.
     */
    public function show(Request $request): JsonResponse
    {
        $base = self::resolveBase($request);

        return response()->json($this->serializeBase($base));
    }

    /**
     * Resolve (creating if needed) the authenticated player's base for the
     * requested kind. Defaults to the terrestrial base.
     */
    public static function resolveBase(Request $request): Base
    {
        $kind = $request->input('kind', $request->query('kind', 'terrestrial'));
        $kind = in_array($kind, ['terrestrial', 'planetary'], true) ? $kind : 'terrestrial';

        return Base::firstOrCreate([
            'user_id' => $request->user()->id,
            'kind' => $kind,
        ]);
    }

    /**
     * Build a full snapshot of the base state used by the frontend.
     */
    public static function serializeBase(Base $base): array
    {
        $base->load('structures.type.levelConfigs');

        // Lazily finalize any build/upgrade whose timer has elapsed.
        $settled = false;
        foreach ($base->structures as $structure) {
            if ($structure->settle()) {
                $settled = true;
            }
        }
        if ($settled) {
            $base->load('structures.type.levelConfigs');
        }

        $maxStructures = $base->effectiveMaxStructures();
        $commandLevel = $base->commandLevel();
        $busyCount = $base->busyStructuresCount();
        $maxConcurrentBuilds = $base->maxConcurrentBuilds();

        $structures = $base->structures->map(fn ($s) => [
            'id' => $s->id,
            'x' => $s->x,
            'y' => $s->y,
            'level' => $s->level,
            'max_level' => $s->type->max_level,
            'is_max_level' => $s->isMaxLevel(),
            // Non-command structures are capped at the Command Center's level.
            'capped_by_command' => ! $s->type->isCommand()
                && $s->level >= $commandLevel
                && ! $s->isMaxLevel(),
            'production_per_minute' => $s->productionPerMinute(),
            'max_capacity' => $s->maxCapacity(),
            'protection' => $s->protection(),
            // Combat stats (0 for structures without HP/damage, e.g. terrestrial).
            'max_hp' => $s->maxHp(),
            'current_hp' => $s->currentHp(),
            'damage' => $s->damage(),
            'upgrade_cost' => $s->upgradeCost(), // {gold, metal, energy} | null
            'upgrade_time' => $s->upgradeTime(), // seconds | null
            'demolition_refund' => $s->demolitionRefund(), // {gold, metal, energy}
            'last_collected_at' => optional($s->last_collected_at)->toIso8601String(),
            'pending' => $s->pendingProduction(),
            // Construction / upgrade state
            'is_constructed' => $s->is_constructed,
            'is_busy' => $s->isBusy(),
            'busy_until' => optional($s->busy_until)->toIso8601String(),
            'remaining_seconds' => $s->remainingSeconds(),
            'busy_kind' => ! $s->is_constructed ? 'build' : ($s->pending_level !== null ? 'upgrade' : null),
            'pending_level' => $s->pending_level,
            'type' => [
                'id' => $s->type->id,
                'key' => $s->type->key,
                'name' => $s->type->name,
                'category' => $s->type->category,
                'width' => $s->type->width,
                'height' => $s->type->height,
                'resource' => $s->type->resource,
                'color' => $s->type->color,
                'scope' => $s->type->scope,
            ],
        ])->values();

        // Catalog is filtered to the base's scope so the planetary base only
        // lists defense structures and the terrestrial base only lists economy.
        $scope = $base->scope();
        $types = StructureType::where('scope', $scope)->orderBy('id')->get()->map(fn ($t) => [
            'id' => $t->id,
            'key' => $t->key,
            'name' => $t->name,
            'description' => $t->description,
            'category' => $t->category,
            'scope' => $t->scope,
            'width' => $t->width,
            'height' => $t->height,
            'resource' => $t->resource,
            'max_level' => $t->max_level,
            'color' => $t->color,
            'build_time' => $t->build_time,
            'is_unique' => $t->is_unique,
        ])->values();

        // Type ids already placed on the base (for disabling unique types in UI).
        $existingTypeIds = $base->structures->pluck('structure_type_id')->unique()->values();

        // Per-category usage vs. limit for command/storage (for disabling full
        // categories in the UI).
        $categoryLimits = [];
        foreach (['command', 'storage'] as $category) {
            $categoryLimits[$category] = [
                'used' => $base->countForCategory($category),
                'max' => $base->maxForCategory($category),
            ];
        }

        // Per-type limits keyed by type id so the UI can disable an individual
        // type once its own limit is hit:
        //  - terrestrial producers: up to N of each.
        //  - planetary defenses: 3 per Defense Center level (1 per 3 levels for
        //    the Cosmic Ray).
        $typeLimits = [];
        foreach ($types as $t) {
            if ($t['category'] === 'producer') {
                $typeLimits[$t['id']] = [
                    'used' => $base->countForType($t['id']),
                    'max' => $base->maxForProducerType('producer'),
                ];
            } elseif ($t['category'] === 'defense') {
                $typeLimits[$t['id']] = [
                    'used' => $base->countForTypeKey($t['key']),
                    'max' => $base->maxForDefenseKey($t['key']),
                ];
            }
        }

        // Resources are a single shared player stockpile, kept on the
        // terrestrial base. The planetary base spends from and reports it too.
        $wallet = $base->wallet();

        return [
            'base' => [
                'id' => $base->id,
                'kind' => $base->kind,
                'scope' => $scope,
                'width' => $base->width,
                'height' => $base->height,
                'resources' => [
                    'gold' => $wallet->gold,
                    'metal' => $wallet->metal,
                    'energy' => $wallet->energy,
                ],
                'total_collected' => [
                    'gold' => $wallet->total_gold_collected,
                    'metal' => $wallet->total_metal_collected,
                    'energy' => $wallet->total_energy_collected,
                ],
                'protection' => $base->totalProtection(),
                'structures_used' => $base->structures->count(),
                'max_structures' => $maxStructures,
                'command_level' => $commandLevel,
                // Defense Center level considered for quantity rules (<= 15).
                'quantity_command_level' => $base->quantityCommandLevel(),
                'existing_type_ids' => $existingTypeIds,
                'category_limits' => $categoryLimits,
                'type_limits' => $typeLimits,
                'builds_in_progress' => $busyCount,
                'max_concurrent_builds' => $maxConcurrentBuilds,
            ],
            'structures' => $structures,
            'structure_types' => $types,
        ];
    }
}
