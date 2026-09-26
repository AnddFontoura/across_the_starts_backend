<?php

namespace App\Http\Controllers;

use App\Models\Base;
use App\Services\PlanetPlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read model for the galaxy view: the 30 quadrants and the planets inside a
 * chosen quadrant. Also resolves a single planet as a potential attack target,
 * which is the foundation for planet-vs-planet combat.
 */
class GalaxyController extends Controller
{
    /**
     * Overview of the galaxy: how many planets each quadrant holds, plus the
     * quadrant the current player lives in (so the UI can jump straight to it).
     */
    public function index(Request $request): JsonResponse
    {
        $counts = Base::query()
            ->where('kind', 'terrestrial')
            ->whereNotNull('quadrant')
            ->selectRaw('quadrant, count(*) as total')
            ->groupBy('quadrant')
            ->pluck('total', 'quadrant');

        $quadrants = [];
        for ($q = 1; $q <= PlanetPlacementService::QUADRANTS; $q++) {
            $quadrants[] = [
                'quadrant' => $q,
                'planets' => (int) ($counts[$q] ?? 0),
                'capacity' => PlanetPlacementService::SLOTS_PER_QUADRANT,
            ];
        }

        $self = $this->selfBase($request);

        return response()->json([
            'quadrants' => $quadrants,
            'total_quadrants' => PlanetPlacementService::QUADRANTS,
            'slots_per_quadrant' => PlanetPlacementService::SLOTS_PER_QUADRANT,
            'self' => $self ? [
                'base_id' => $self->id,
                'quadrant' => $self->quadrant,
                'slot' => $self->slot,
            ] : null,
        ]);
    }

    /**
     * The planets inside a single quadrant. Returns one entry per occupied slot
     * so the frontend can lay them out on the quadrant grid, leaving empty slots
     * as open space.
     */
    public function show(Request $request, int $quadrant): JsonResponse
    {
        $quadrant = $this->clampQuadrant($quadrant);
        $selfId = $this->selfBase($request)?->id;

        $planets = Base::query()
            ->where('kind', 'terrestrial')
            ->where('quadrant', $quadrant)
            ->whereNotNull('slot')
            ->with('user:id,name')
            ->orderBy('slot')
            ->get()
            ->map(fn (Base $b) => [
                'base_id' => $b->id,
                'slot' => $b->slot,
                'owner_name' => $b->user?->name ?? 'Desconhecido',
                'is_self' => $b->id === $selfId,
            ])
            ->values();

        return response()->json([
            'quadrant' => $quadrant,
            'capacity' => PlanetPlacementService::SLOTS_PER_QUADRANT,
            'planets' => $planets,
        ]);
    }

    /**
     * Resolve a single planet as an attack target. This is the foundation for
     * planet-vs-planet combat: it reports whether the planet can be attacked by
     * the current player and returns the defender's summary. The actual battle
     * launch will build on this endpoint.
     */
    public function target(Request $request, Base $base): JsonResponse
    {
        // Only terrestrial planets are galaxy targets.
        if ($base->kind !== 'terrestrial' || $base->quadrant === null) {
            abort(404, 'Este planeta não existe na galáxia.');
        }

        $base->loadMissing('user:id,name');
        $isSelf = $base->user_id === $request->user()->id;

        // The defensive (orbital) base of the target owner, if any — this is
        // what an attacker would actually engage.
        $defenseBase = Base::query()
            ->where('user_id', $base->user_id)
            ->where('kind', 'planetary')
            ->first();

        return response()->json([
            'planet' => [
                'base_id' => $base->id,
                'quadrant' => $base->quadrant,
                'slot' => $base->slot,
                'owner_name' => $base->user?->name ?? 'Desconhecido',
                'is_self' => $isSelf,
            ],
            // Attack rules foundation: you can't attack yourself; a target is
            // "attackable" when it belongs to another player. Richer rules
            // (shields, cooldowns, distance) will layer on here later.
            'attack' => [
                'attackable' => ! $isSelf,
                'reason' => $isSelf ? 'Você não pode atacar o seu próprio planeta.' : null,
                'has_defense_base' => $defenseBase !== null,
            ],
        ]);
    }

    // --- helpers ------------------------------------------------------------

    protected function selfBase(Request $request): ?Base
    {
        return Base::query()
            ->where('user_id', $request->user()->id)
            ->where('kind', 'terrestrial')
            ->first();
    }

    protected function clampQuadrant(int $quadrant): int
    {
        return max(1, min(PlanetPlacementService::QUADRANTS, $quadrant));
    }
}
