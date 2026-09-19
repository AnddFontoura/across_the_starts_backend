<?php

namespace App\Http\Controllers;

use App\Models\Base;
use App\Models\GameSetting;
use App\Models\Structure;
use App\Models\StructureType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StructureController extends Controller
{
    /**
     * Place a new structure on the player's base at (x, y).
     * Rejects out-of-bounds and overlapping placements.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'structure_type_id' => ['required', 'integer', 'exists:structure_types,id'],
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
        ]);

        $base = Base::firstOrCreate(['user_id' => $request->user()->id]);
        $type = StructureType::findOrFail($data['structure_type_id']);

        // Enforce the global max number of structures per base (in-progress
        // builds count toward the limit).
        $maxStructures = (int) GameSetting::get('max_structures_per_base', 20);
        if ($base->structures()->count() >= $maxStructures) {
            throw ValidationException::withMessages([
                'limit' => ["Limite de construções atingido (máximo {$maxStructures})."],
            ]);
        }

        if (! $base->fitsInBounds($data['x'], $data['y'], $type->width, $type->height)) {
            throw ValidationException::withMessages([
                'position' => ['A estrutura não cabe dentro dos limites do terreno.'],
            ]);
        }

        if ($base->hasOverlap($data['x'], $data['y'], $type->width, $type->height)) {
            throw ValidationException::withMessages([
                'position' => ['Já existe uma estrutura nessa posição. Escolha outro local.'],
            ]);
        }

        // Apply build time: the structure starts under construction and only
        // becomes active (produces/protects) when the timer elapses.
        $buildTime = (int) $type->build_time;
        $now = now();

        $structure = Structure::create([
            'base_id' => $base->id,
            'structure_type_id' => $type->id,
            'level' => 1,
            'x' => $data['x'],
            'y' => $data['y'],
            'last_collected_at' => $now,
            'is_constructed' => $buildTime <= 0,
            'busy_until' => $buildTime > 0 ? $now->copy()->addSeconds($buildTime) : null,
        ]);

        return response()->json([
            'message' => $buildTime > 0 ? 'Construção iniciada.' : 'Estrutura posicionada.',
            'structure_id' => $structure->id,
            ...BaseController::serializeBase($base->fresh()),
        ], 201);
    }

    /**
     * Collect accumulated resources from a single structure.
     */
    public function collect(Request $request, Structure $structure): JsonResponse
    {
        $base = $this->authorizeStructure($request, $structure);

        $this->collectStructure($structure, $base);

        return response()->json([
            'message' => 'Recursos coletados.',
            ...BaseController::serializeBase($base->fresh()),
        ]);
    }

    /**
     * Collect accumulated resources from every structure on the base.
     */
    public function collectAll(Request $request): JsonResponse
    {
        $base = Base::firstOrCreate(['user_id' => $request->user()->id]);
        $base->load('structures.type.levelConfigs');

        DB::transaction(function () use ($base) {
            foreach ($base->structures as $structure) {
                $this->collectStructure($structure, $base);
            }
        });

        return response()->json([
            'message' => 'Recursos coletados.',
            ...BaseController::serializeBase($base->fresh()),
        ]);
    }

    /**
     * Upgrade a structure to the next level, paying the upgrade cost from the
     * player's balance of the structure's own resource.
     */
    public function upgrade(Request $request, Structure $structure): JsonResponse
    {
        $base = $this->authorizeStructure($request, $structure);

        // Can't act on a structure that's still building or already upgrading.
        if ($structure->isBusy()) {
            throw ValidationException::withMessages([
                'busy' => ['Esta estrutura ainda está em obras. Aguarde a conclusão.'],
            ]);
        }

        if ($structure->isMaxLevel()) {
            throw ValidationException::withMessages([
                'level' => ['Esta estrutura já está no nível máximo.'],
            ]);
        }

        $cost = $structure->upgradeCost(); // ['gold' => .., 'metal' => .., 'energy' => ..]

        // Check the player can afford every required resource.
        $labels = ['gold' => 'ouro', 'metal' => 'metal', 'energy' => 'energia'];
        $missing = [];
        foreach ($cost as $resource => $amount) {
            if ($amount > 0 && $base->{$resource} < $amount) {
                $missing[] = "{$amount} de {$labels[$resource]}";
            }
        }

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'cost' => ['Recursos insuficientes. Necessário: '.implode(', ', $missing).'.'],
            ]);
        }

        $upgradeTime = (int) ($structure->upgradeTime() ?? 0);

        DB::transaction(function () use ($structure, $base, $cost, $upgradeTime) {
            // Collect pending production first so the player doesn't lose it.
            $this->collectStructure($structure, $base);

            // Debit the cost immediately when the upgrade starts.
            foreach ($cost as $resource => $amount) {
                if ($amount > 0) {
                    $base->decrement($resource, $amount);
                }
            }

            if ($upgradeTime > 0) {
                // Start a timed upgrade: level rises when the timer elapses.
                $structure->pending_level = $structure->level + 1;
                $structure->busy_until = now()->addSeconds($upgradeTime);
            } else {
                // Instant upgrade.
                $structure->level = $structure->level + 1;
            }
            $structure->save();
        });

        return response()->json([
            'message' => $upgradeTime > 0 ? 'Evolução iniciada.' : 'Estrutura evoluída.',
            ...BaseController::serializeBase($base->fresh()),
        ]);
    }

    /**
     * Apply production of a structure to the base. Production is capped at the
     * structure's maximum capacity for its level; resetting the timer means any
     * overflow beyond the cap is discarded ("storage full").
     */
    protected function collectStructure(Structure $structure, Base $base): void
    {
        $amount = $structure->pendingProduction();
        if ($amount <= 0) {
            return;
        }

        $base->creditResource($structure->type->resource, $amount);

        $structure->last_collected_at = now();
        $structure->save();
    }

    /**
     * Ensure the structure belongs to the authenticated player's base.
     */
    protected function authorizeStructure(Request $request, Structure $structure): Base
    {
        $base = Base::firstOrCreate(['user_id' => $request->user()->id]);

        if ($structure->base_id !== $base->id) {
            abort(403, 'Essa estrutura não pertence a você.');
        }

        $structure->load('type.levelConfigs');

        return $base;
    }
}
