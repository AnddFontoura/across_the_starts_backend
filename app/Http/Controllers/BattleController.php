<?php

namespace App\Http\Controllers;

use App\Models\BattleFleet;
use App\Models\BattleInstance;
use App\Models\BattleShip;
use App\Models\InvestigationDefinition;
use App\Services\BattleService;
use App\Services\BattleSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Player-facing "Investigação Interplanetária" endpoints. The battle is
 * server-authoritative and advances one round at a time as the player watches
 * (POST /step). Leaving and coming back resumes the same run (GET /active).
 */
class BattleController extends Controller
{
    public function __construct(
        protected BattleSetupService $setup,
        protected BattleService $battle,
    ) {
    }

    /** Investigations available to launch, plus any run already in progress. */
    public function index(Request $request): JsonResponse
    {
        $definitions = InvestigationDefinition::where('is_active', true)
            ->with(['enemyFleets.slots', 'prizes.item'])
            ->orderBy('id')
            ->get()
            ->map(fn (InvestigationDefinition $d) => $this->serializeDefinition($d))
            ->values();

        return response()->json([
            'investigations' => $definitions,
            'active' => $this->activeInstancePayload($request),
        ]);
    }

    /** Launch an investigation with the chosen player fleets. */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'investigation_id' => ['required', 'integer', 'exists:investigation_definitions,id'],
            'fleet_ids' => ['required', 'array', 'min:1'],
            'fleet_ids.*' => ['integer', 'exists:fleets,id'],
        ]);

        $definition = InvestigationDefinition::with('enemyFleets.slots.baseType')
            ->findOrFail($data['investigation_id']);

        $instance = $this->setup->start($request->user(), $definition, $data['fleet_ids']);

        return response()->json([
            'message' => 'Investigação iniciada.',
            'battle' => $this->serializeInstance($instance),
        ], 201);
    }

    /** The player's in-progress (or most recently finished, unclaimed) run. */
    public function active(Request $request): JsonResponse
    {
        return response()->json([
            'active' => $this->activeInstancePayload($request),
        ]);
    }

    /** Full state of a specific battle instance (for the viewer). */
    public function show(Request $request, BattleInstance $battle): JsonResponse
    {
        $this->authorizeInstance($request, $battle);

        return response()->json([
            'battle' => $this->serializeInstance($battle),
        ]);
    }

    /** Advance the battle by one round and return the updated state + new events. */
    public function step(Request $request, BattleInstance $battle): JsonResponse
    {
        $this->authorizeInstance($request, $battle);

        if ($battle->isOver()) {
            return response()->json([
                'message' => 'A batalha já terminou.',
                'battle' => $this->serializeInstance($battle),
            ], 422);
        }

        $before = (int) ($battle->events()->max('sequence') ?? 0);
        $battle = $this->battle->advanceRound($battle);

        $newEvents = $battle->events()
            ->where('sequence', '>', $before)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($e) => $this->serializeEvent($e))
            ->values();

        return response()->json([
            'battle' => $this->serializeInstance($battle),
            'events' => $newEvents,
        ]);
    }

    /** Claim the win rewards (item prizes + commander experience). */
    public function claim(Request $request, BattleInstance $battle): JsonResponse
    {
        $this->authorizeInstance($request, $battle);

        if ($battle->status !== BattleInstance::STATUS_WON) {
            return response()->json(['message' => 'Não há recompensas a resgatar.'], 422);
        }
        if ($battle->rewards_claimed) {
            return response()->json(['message' => 'Recompensas já resgatadas.'], 422);
        }

        $rewards = $this->battle->claimRewards($battle);

        return response()->json([
            'message' => 'Recompensas resgatadas.',
            'rewards' => $rewards,
            'battle' => $this->serializeInstance($battle->fresh()),
        ]);
    }

    /**
     * Abandon an in-progress run. Counts as a loss: losses are settled (ships
     * that would have been destroyed stay as-is at current state) and the
     * fleets unlock. We simply mark it abandoned and settle current losses.
     */
    public function abandon(Request $request, BattleInstance $battle): JsonResponse
    {
        $this->authorizeInstance($request, $battle);

        if ($battle->isOver()) {
            return response()->json(['message' => 'A batalha já terminou.'], 422);
        }

        $battle->status = BattleInstance::STATUS_ABANDONED;
        $battle->finished_at = now();
        $battle->save();
        $this->battle->settleLosses($battle);

        return response()->json([
            'message' => 'Investigação abandonada.',
            'battle' => $this->serializeInstance($battle->fresh()),
        ]);
    }

    // --- helpers ------------------------------------------------------------

    protected function authorizeInstance(Request $request, BattleInstance $battle): void
    {
        if ($battle->user_id !== $request->user()->id) {
            abort(403, 'Esta investigação não pertence a você.');
        }
    }

    /** The active run for the current user, or the latest unclaimed win. */
    protected function activeInstancePayload(Request $request): ?array
    {
        $instance = BattleInstance::where('user_id', $request->user()->id)
            ->where('status', BattleInstance::STATUS_IN_PROGRESS)
            ->latest('id')
            ->first();

        // Also surface a just-finished win whose rewards are unclaimed, so the
        // player can come back and collect.
        if (! $instance) {
            $instance = BattleInstance::where('user_id', $request->user()->id)
                ->where('status', BattleInstance::STATUS_WON)
                ->where('rewards_claimed', false)
                ->latest('id')
                ->first();
        }

        return $instance ? $this->serializeInstance($instance) : null;
    }

    protected function serializeDefinition(InvestigationDefinition $d): array
    {
        return [
            'id' => $d->id,
            'key' => $d->key,
            'name' => $d->name,
            'description' => $d->description,
            'min_player_fleets' => $d->min_player_fleets,
            'max_player_fleets' => $d->max_player_fleets,
            'max_rounds' => $d->max_rounds,
            'exp_reward' => $d->exp_reward,
            'map' => ['width' => $d->map_width, 'height' => $d->map_height],
            'image_url' => $d->image_url,
            'color' => $d->color,
            'enemy_fleets' => $d->enemyFleets->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'ships' => $e->slots->sum('quantity'),
            ])->values(),
            'prizes' => $d->prizes->map(fn ($p) => [
                'item' => $p->item?->name,
                'quantity' => $p->quantity,
                'chance' => $p->chance,
            ])->values(),
        ];
    }

    protected function serializeInstance(BattleInstance $instance): array
    {
        $instance->loadMissing(['fleets.ships', 'definition']);

        return [
            'id' => $instance->id,
            'status' => $instance->status,
            'is_over' => $instance->isOver(),
            'current_round' => $instance->current_round,
            'max_rounds' => $instance->max_rounds,
            'rewards_claimed' => $instance->rewards_claimed,
            'map' => ['width' => $instance->map_width, 'height' => $instance->map_height],
            'investigation' => [
                'id' => $instance->definition?->id,
                'name' => $instance->definition?->name,
                'exp_reward' => $instance->definition?->exp_reward,
            ],
            'fleets' => $instance->fleets->map(fn (BattleFleet $f) => $this->serializeFleet($f))->values(),
        ];
    }

    protected function serializeFleet(BattleFleet $f): array
    {
        return [
            'id' => $f->id,
            'side' => $f->side,
            'name' => $f->name,
            'x' => $f->x,
            'y' => $f->y,
            'movement' => $f->movement,
            'velocidade' => $f->velocidade,
            'alive' => $f->alive,
            'ships' => $f->ships->map(fn (BattleShip $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'class' => $s->ship_class,
                'weapon_type' => $s->weapon_type,
                'weapon_range' => $s->weapon_range,
                'attack' => $s->attack,
                'quantity' => $s->quantity,
                'quantity_remaining' => $s->quantity_remaining,
            ])->values(),
            'ships_remaining' => $f->ships->sum('quantity_remaining'),
        ];
    }

    protected function serializeEvent($e): array
    {
        return [
            'sequence' => $e->sequence,
            'round' => $e->round,
            'type' => $e->type,
            'actor_fleet_id' => $e->actor_fleet_id,
            'target_fleet_id' => $e->target_fleet_id,
            'payload' => $e->payload,
        ];
    }
}
