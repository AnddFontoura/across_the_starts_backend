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
        $base = Base::firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json($this->serializeBase($base));
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
            ],
        ])->values();

        $types = StructureType::orderBy('id')->get()->map(fn ($t) => [
            'id' => $t->id,
            'key' => $t->key,
            'name' => $t->name,
            'description' => $t->description,
            'category' => $t->category,
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

        // Producers are limited per type: expose used/max keyed by type id so
        // the UI can disable an individual producer once its own limit is hit.
        $typeLimits = [];
        foreach ($types as $t) {
            if ($t['category'] === 'producer') {
                $typeLimits[$t['id']] = [
                    'used' => $base->countForType($t['id']),
                    'max' => $base->maxForProducerType('producer'),
                ];
            }
        }

        return [
            'base' => [
                'id' => $base->id,
                'width' => $base->width,
                'height' => $base->height,
                'resources' => [
                    'gold' => $base->gold,
                    'metal' => $base->metal,
                    'energy' => $base->energy,
                ],
                'total_collected' => [
                    'gold' => $base->total_gold_collected,
                    'metal' => $base->total_metal_collected,
                    'energy' => $base->total_energy_collected,
                ],
                'protection' => $base->totalProtection(),
                'structures_used' => $base->structures->count(),
                'max_structures' => $maxStructures,
                'command_level' => $commandLevel,
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
