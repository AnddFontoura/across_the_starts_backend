<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Models\Commander;
use App\Models\Fleet;
use App\Models\FleetSlot;
use App\Models\ShipDesign;
use App\Services\FleetCompositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FleetController extends Controller
{
    public function __construct(protected FleetCompositionService $composition)
    {
    }

    /**
     * The player's fleets, available ships (owned minus reserved), and
     * commanders to lead them.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    /**
     * Create a fleet with a name, an optional commander, and a composition.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $this->validatePayload($request);
        $this->composition->validate($user, $data['slots']);

        $spawn = $this->spawnPosition($user);

        $fleet = DB::transaction(function () use ($user, $data, $spawn) {
            $fleet = Fleet::create([
                'user_id' => $user->id,
                'commander_id' => $data['commander_id'],
                'name' => $data['name'],
                'x' => $spawn['x'],
                'y' => $spawn['y'],
            ]);
            $this->syncSlots($fleet, $data['slots']);

            return $fleet;
        });

        return response()->json([
            'message' => 'Frota criada.',
            'fleet' => $this->serializeFleet($fleet->fresh()),
            ...$this->snapshot($request),
        ], 201);
    }

    /**
     * Update a fleet (rename, change commander, recompose).
     */
    public function update(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeFleet($request, $fleet);
        $user = $request->user();

        $data = $this->validatePayload($request);
        // Exclude this fleet's current reservations from the availability check.
        $this->composition->validate($user, $data['slots'], $fleet->id);

        DB::transaction(function () use ($fleet, $data) {
            $fleet->update([
                'commander_id' => $data['commander_id'],
                'name' => $data['name'],
            ]);
            $fleet->slots()->delete();
            $this->syncSlots($fleet, $data['slots']);
        });

        return response()->json([
            'message' => 'Frota atualizada.',
            'fleet' => $this->serializeFleet($fleet->fresh()),
            ...$this->snapshot($request),
        ]);
    }

    /**
     * Disband a fleet (frees its reserved ships).
     */
    public function destroy(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeFleet($request, $fleet);
        $fleet->delete();

        return response()->json([
            'message' => 'Frota desfeita.',
            ...$this->snapshot($request),
        ]);
    }

    /**
     * Move a fleet's marker on the planetary base map. Fleets overlap freely
     * (no collision); the position is only clamped to the base bounds.
     */
    public function move(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorizeFleet($request, $fleet);

        $data = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
        ]);

        $base = \App\Models\Base::where('user_id', $request->user()->id)->where('kind', 'planetary')->first();
        $maxX = $base ? (int) $base->width - Fleet::SIZE : $data['x'];
        $maxY = $base ? (int) $base->height - Fleet::SIZE : $data['y'];

        $fleet->x = max(0, min($data['x'], max(0, $maxX)));
        $fleet->y = max(0, min($data['y'], max(0, $maxY)));
        $fleet->save();

        return response()->json([
            'message' => 'Frota reposicionada.',
            'fleet' => $this->serializeFleet($fleet->fresh()),
        ]);
    }

    // --- helpers ---

    protected function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'commander_id' => ['nullable', 'integer', 'exists:commanders,id'],
            'slots' => ['required', 'array', 'min:1', 'max:'.Fleet::MAX_SLOTS],
            'slots.*.ship_design_id' => ['required', 'integer', 'exists:ship_designs,id'],
            'slots.*.quantity' => ['required', 'integer', 'min:1', 'max:'.Fleet::MAX_PER_SLOT],
        ]);

        // A commander, if given, must belong to the player.
        if (! empty($data['commander_id'])) {
            $owns = Commander::where('id', $data['commander_id'])
                ->where('user_id', $request->user()->id)
                ->exists();
            if (! $owns) {
                abort(403, 'Esse comandante não pertence a você.');
            }
        }

        $data['commander_id'] = $data['commander_id'] ?? null;

        return $data;
    }

    protected function syncSlots(Fleet $fleet, array $slots): void
    {
        foreach ($slots as $s) {
            FleetSlot::create([
                'fleet_id' => $fleet->id,
                'ship_design_id' => (int) $s['ship_design_id'],
                'quantity' => (int) $s['quantity'],
            ]);
        }
    }

    protected function authorizeFleet(Request $request, Fleet $fleet): void
    {
        if ($fleet->user_id !== $request->user()->id) {
            abort(403, 'Essa frota não pertence a você.');
        }
    }

    protected function serializeFleet(Fleet $fleet): array
    {
        $fleet->loadMissing(['commander.definition', 'slots.design.baseType']);

        $slots = $fleet->slots->map(fn (FleetSlot $s) => [
            'ship_design_id' => $s->ship_design_id,
            'quantity' => $s->quantity,
            'design_name' => $s->design->name,
            'class' => $s->design->baseType->class,
        ])->all();

        $summary = $this->composition->summarize(
            array_map(fn ($s) => ['ship_design_id' => $s['ship_design_id'], 'quantity' => $s['quantity']], $slots),
            $fleet->commander,
            $fleet->user,
        );

        return [
            'id' => $fleet->id,
            'name' => $fleet->name,
            'commander_id' => $fleet->commander_id,
            'commander_name' => $fleet->commander?->name,
            'x' => $fleet->x,
            'y' => $fleet->y,
            'slots' => $slots,
            'summary' => $summary,
        ];
    }

    /**
     * Spawn position for a new fleet: the free cell just to the right of the
     * planetary base's Defense Center, clamped to bounds. Falls back to a
     * corner when there is no command structure yet.
     */
    protected function spawnPosition(\App\Models\User $user): array
    {
        $base = \App\Models\Base::where('user_id', $user->id)->where('kind', 'planetary')->first();
        if (! $base) {
            return ['x' => 0, 'y' => 0];
        }

        $base->load('structures.type');
        $command = $base->commandStructure();

        if ($command) {
            // Just to the right of the Defense Center, vertically centered.
            $x = $command->x + $command->type->width + 2;
            $y = $command->y + intdiv(max(0, $command->type->height - Fleet::SIZE), 2);
        } else {
            $x = 2;
            $y = 2;
        }

        return [
            'x' => max(0, min($x, (int) $base->width - Fleet::SIZE)),
            'y' => max(0, min($y, (int) $base->height - Fleet::SIZE)),
        ];
    }

    protected function snapshot(Request $request): array
    {
        $user = $request->user();

        $fleets = Fleet::where('user_id', $user->id)
            ->with(['commander.definition', 'slots.design.baseType'])
            ->orderBy('id')
            ->get()
            ->map(fn (Fleet $f) => $this->serializeFleet($f))
            ->values();

        // Available ships: owned by design minus what fleets reserve.
        $designs = ShipDesign::where('user_id', $user->id)
            ->with('baseType')
            ->orderBy('id')
            ->get()
            ->map(fn (ShipDesign $d) => [
                'ship_design_id' => $d->id,
                'name' => $d->name,
                'class' => $d->baseType->class,
                'owned' => (int) Aircraft::where('user_id', $user->id)->where('ship_design_id', $d->id)->sum('quantity'),
                'available' => $this->composition->availableForDesign($user, $d->id),
            ])
            ->values();

        $commanders = Commander::where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Commander $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'rank_label' => Commander::ROMAN[$c->rank] ?? (string) $c->rank,
            ])
            ->values();

        return [
            'fleets' => $fleets,
            'available_ships' => $designs,
            'commanders' => $commanders,
            'limits' => [
                'max_slots' => Fleet::MAX_SLOTS,
                'max_per_slot' => Fleet::MAX_PER_SLOT,
            ],
        ];
    }
}
