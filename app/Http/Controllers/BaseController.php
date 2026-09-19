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

        $maxStructures = (int) \App\Models\GameSetting::get('max_structures_per_base', 20);

        $structures = $base->structures->map(fn ($s) => [
            'id' => $s->id,
            'x' => $s->x,
            'y' => $s->y,
            'level' => $s->level,
            'max_level' => $s->type->max_level,
            'is_max_level' => $s->isMaxLevel(),
            'production_per_hour' => $s->productionPerHour(),
            'max_capacity' => $s->maxCapacity(),
            'protection' => $s->protection(),
            'upgrade_cost' => $s->upgradeCost(), // {gold, metal, energy} | null
            'upgrade_time' => $s->upgradeTime(), // seconds | null
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
        ])->values();

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
            ],
            'structures' => $structures,
            'structure_types' => $types,
        ];
    }
}
