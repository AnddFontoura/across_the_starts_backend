<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Models\AircraftBuildOrder;
use App\Models\AircraftMatchup;
use App\Models\AircraftType;
use App\Models\Base;
use App\Models\ShipDesign;
use App\Services\FleetService;
use App\Services\ShipDesignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AircraftController extends Controller
{
    public function __construct(
        protected FleetService $fleet,
        protected ShipDesignService $designs,
    ) {
    }

    /**
     * Full fleet snapshot: aircraft type catalog, counter matrix, the player's
     * owned aircraft, in-progress build orders, and hangar-derived limits.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Finalize any completed builds first.
        $this->fleet->settle($user);

        return response()->json($this->snapshot($request));
    }

    /**
     * Enqueue building N aircraft from a saved ship design. Cost and build time
     * come from the design's aggregated summary (base hull + modules), reduced
     * by the hangar. Each slot builds sequentially; with S slots up to S queues
     * run in parallel. Resources are debited up front. Capacity counts ready +
     * in-progress.
     */
    public function build(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'ship_design_id' => ['required', 'integer', 'exists:ship_designs,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        // Settle completed builds so capacity/slots reflect reality.
        $this->fleet->settle($user);

        $design = ShipDesign::with(['baseType', 'modules.moduleType'])->findOrFail($data['ship_design_id']);
        if ($design->user_id !== $user->id) {
            abort(403, 'Esse modelo não pertence a você.');
        }
        $quantity = (int) $data['quantity'];

        // Must have a built Aircraft Hangar.
        if ($this->fleet->hangar($user) === null) {
            throw ValidationException::withMessages([
                'hangar' => ['Construa um Hangar de Aeronaves na base terrestre para produzir naves.'],
            ]);
        }

        $slots = $this->fleet->buildSlots($user);
        if ($slots <= 0) {
            throw ValidationException::withMessages([
                'hangar' => ['O Hangar não possui slots de construção disponíveis.'],
            ]);
        }

        // Capacity check (ready + in progress + the new order).
        $remaining = $this->fleet->remainingCapacity($user);
        if ($quantity > $remaining) {
            throw ValidationException::withMessages([
                'capacity' => ["Capacidade da frota insuficiente. Livre: {$remaining}."],
            ]);
        }

        // Cost/time come from the design summary (base hull + modules).
        $wallet = Base::firstOrCreate(['user_id' => $user->id, 'kind' => 'terrestrial']);
        $cost = $this->fleet->designCost($design);
        $labels = ['gold' => 'ouro', 'metal' => 'metal', 'energy' => 'energia'];
        $totals = [
            'gold' => $cost['gold'] * $quantity,
            'metal' => $cost['metal'] * $quantity,
            'energy' => $cost['energy'] * $quantity,
        ];

        $missing = [];
        foreach ($totals as $resource => $amount) {
            if ($amount > 0 && $wallet->{$resource} < $amount) {
                $missing[] = "{$amount} de {$labels[$resource]}";
            }
        }
        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'cost' => ['Recursos insuficientes. Necessário: '.implode(', ', $missing).'.'],
            ]);
        }

        $buildTime = $this->fleet->effectiveBuildTimeForDesign($user, $design);
        $now = now();

        DB::transaction(function () use ($wallet, $totals, $quantity, $design, $user, $buildTime, $now) {
            // Debit resources up front.
            foreach ($totals as $resource => $amount) {
                if ($amount > 0) {
                    $wallet->decrement($resource, $amount);
                }
            }

            // Distribute orders across slots, chaining finish times per slot so
            // each slot builds sequentially. Instant builds (time 0) complete now.
            for ($i = 0; $i < $quantity; $i++) {
                $slot = $this->fleet->nextSlot($user, $now) ?? 0;
                $startAt = $this->fleet->slotFreeAt($user, $slot, $now);
                $finishesAt = $startAt->copy()->addSeconds($buildTime);

                AircraftBuildOrder::create([
                    'user_id' => $user->id,
                    'ship_design_id' => $design->id,
                    'aircraft_type_id' => $design->aircraft_type_id,
                    'slot' => $slot,
                    'finishes_at' => $finishesAt,
                ]);
            }
        });

        // Instant builds settle immediately.
        $this->fleet->settle($user);

        return response()->json([
            'message' => $buildTime > 0
                ? "Construção de {$quantity}x {$design->name} iniciada."
                : "{$quantity}x {$design->name} construída(s).",
            ...$this->snapshot($request),
        ], 201);
    }

    /**
     * Build the JSON snapshot used by the fleet UI.
     */
    protected function snapshot(Request $request): array
    {
        $user = $request->user();

        $types = AircraftType::orderBy('id')->get();

        // Owned aircraft, keyed by design.
        $ownedByDesign = Aircraft::where('user_id', $user->id)
            ->get()
            ->keyBy('ship_design_id');

        $now = now();
        $orders = AircraftBuildOrder::where('user_id', $user->id)
            ->orderBy('finishes_at')
            ->get();

        // In-progress counts per design.
        $inProgressByDesign = $orders->groupBy('ship_design_id')->map->count();

        $matchups = AircraftMatchup::all();

        // The player's designs, each with owned + in-progress counts and the
        // aggregated summary (cost/time reflect the hangar reduction on time).
        $designs = ShipDesign::where('user_id', $user->id)
            ->with(['baseType', 'modules.moduleType'])
            ->orderBy('id')
            ->get()
            ->map(function (ShipDesign $d) use ($user, $ownedByDesign, $inProgressByDesign) {
                $serialized = $this->designs->serialize($d);
                $serialized['owned'] = (int) ($ownedByDesign[$d->id]->quantity ?? 0);
                $serialized['in_progress'] = (int) ($inProgressByDesign[$d->id] ?? 0);
                $serialized['effective_build_time'] = $this->fleet->effectiveBuildTimeForDesign($user, $d);

                return $serialized;
            })
            ->values();

        return [
            'designs' => $designs,

            'aircraft_types' => $types->map(fn (AircraftType $t) => [
                'id' => $t->id,
                'key' => $t->key,
                'class' => $t->class,
                'name' => $t->name,
                'description' => $t->description,
                'color' => $t->color,
                'image_url' => $t->image_url,
                'storage' => $t->storage,
                'cost' => $t->cost(),
                'build_time' => $t->build_time,
                'effective_build_time' => $this->fleet->effectiveBuildTime($user, $t),
                'shield' => $t->shield,
                'hull' => $t->hull,
                'movement' => $t->movement,
                'special_attributes' => $t->special_attributes,
            ])->values(),

            'matchups' => $matchups->map(fn (AircraftMatchup $m) => [
                'attacker_class' => $m->attacker_class,
                'defender_class' => $m->defender_class,
                'damage_bonus_percent' => $m->damage_bonus_percent,
                'damage_reduction_percent' => $m->damage_reduction_percent,
            ])->values(),

            'build_orders' => $orders->map(fn (AircraftBuildOrder $o) => [
                'id' => $o->id,
                'ship_design_id' => $o->ship_design_id,
                'aircraft_type_id' => $o->aircraft_type_id,
                'slot' => $o->slot,
                'finishes_at' => $o->finishes_at->toIso8601String(),
                'remaining_seconds' => max(0, (int) ceil($now->diffInSeconds($o->finishes_at, false))),
            ])->values(),

            'fleet' => [
                'has_hangar' => $this->fleet->hangar($user) !== null,
                'build_slots' => $this->fleet->buildSlots($user),
                'capacity' => $this->fleet->fleetCapacity($user),
                'used' => $this->fleet->usedCapacity($user),
                'remaining' => $this->fleet->remainingCapacity($user),
                'build_time_reduction' => $this->fleet->buildTimeReduction($user),
                'in_progress' => $this->fleet->inProgressCount($user),
                'owned' => $this->fleet->ownedCount($user),
            ],
        ];
    }
}
