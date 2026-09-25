<?php

namespace App\Services;

use App\Models\Aircraft;
use App\Models\AircraftBuildOrder;
use App\Models\AircraftType;
use App\Models\Base;
use App\Models\ShipDesign;
use App\Models\Structure;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Owns the account-level fleet rules. Aircraft belong to the user (not a base)
 * because they can attack other planets. The Aircraft Hangar (a support
 * structure on the terrestrial base) governs the fleet:
 *  - build slots (parallel build queues)
 *  - fleet capacity (max aircraft: ready + in progress)
 *  - build-time reduction (%)
 */
class FleetService
{
    public function __construct(protected ShipDesignService $designs)
    {
    }

    /**
     * The player's built Aircraft Hangar, if any. The hangar is a unique
     * support structure on the terrestrial base.
     */
    public function hangar(User $user): ?Structure
    {
        $base = Base::where('user_id', $user->id)->where('kind', 'terrestrial')->first();
        if (! $base) {
            return null;
        }

        return $base->structures()
            ->with('type')
            ->get()
            ->first(fn (Structure $s) => $s->type->isSupport() && $s->is_constructed);
    }

    /** Number of parallel build queues (0 if no built hangar). */
    public function buildSlots(User $user): int
    {
        return (int) ($this->hangar($user)?->buildSlots() ?? 0);
    }

    /** Max aircraft the account can hold (0 if no built hangar). */
    public function fleetCapacity(User $user): int
    {
        return (int) ($this->hangar($user)?->fleetCapacity() ?? 0);
    }

    /** Build-time reduction percent (0 if no built hangar). */
    public function buildTimeReduction(User $user): int
    {
        return (int) ($this->hangar($user)?->buildTimeReduction() ?? 0);
    }

    /** Effective build time in seconds for a raw type after the hangar reduction. */
    public function effectiveBuildTime(User $user, AircraftType $type): int
    {
        return $this->applyReduction($user, (int) $type->build_time);
    }

    /**
     * Effective build time (seconds) for a ship design: the design's summed
     * build time (base hull + modules) after the hangar reduction.
     */
    public function effectiveBuildTimeForDesign(User $user, ShipDesign $design): int
    {
        $summary = $this->designSummary($design);

        return $this->applyReduction($user, (int) $summary['build_time']);
    }

    /** Total resource cost for one unit of a design (base hull + modules). */
    public function designCost(ShipDesign $design): array
    {
        return $this->designSummary($design)['cost'];
    }

    /** Aggregated summary (stats/cost/time) for a design. */
    public function designSummary(ShipDesign $design): array
    {
        $design->loadMissing(['baseType', 'modules.moduleType']);
        $modules = $design->modules->map(fn ($dm) => [
            'module' => $dm->moduleType,
            'quantity' => (int) $dm->quantity,
        ])->all();

        return $this->designs->summarize($design->baseType, $modules);
    }

    /** Apply the hangar's build-time reduction to a raw duration. */
    protected function applyReduction(User $user, int $seconds): int
    {
        $reduction = $this->buildTimeReduction($user);

        return max(0, (int) floor($seconds * (100 - $reduction) / 100));
    }

    /** How many aircraft the account currently owns (completed). */
    public function ownedCount(User $user): int
    {
        return (int) Aircraft::where('user_id', $user->id)->sum('quantity');
    }

    /** How many aircraft are currently under construction. */
    public function inProgressCount(User $user): int
    {
        return (int) AircraftBuildOrder::where('user_id', $user->id)->count();
    }

    /** Capacity used: completed + in-progress. */
    public function usedCapacity(User $user): int
    {
        return $this->ownedCount($user) + $this->inProgressCount($user);
    }

    /** Remaining fleet capacity (never negative). */
    public function remainingCapacity(User $user): int
    {
        return max(0, $this->fleetCapacity($user) - $this->usedCapacity($user));
    }

    /**
     * Settle any build orders whose timer elapsed: promote them into the
     * fleet (aircraft.quantity) and delete the orders. Returns how many
     * aircraft were completed.
     */
    public function settle(User $user, ?CarbonInterface $now = null): int
    {
        $now ??= now();

        $due = AircraftBuildOrder::where('user_id', $user->id)
            ->where('finishes_at', '<=', $now)
            ->get();

        if ($due->isEmpty()) {
            return 0;
        }

        $completed = 0;
        DB::transaction(function () use ($due, $user, &$completed) {
            // Group completed orders by design so the fleet is keyed by model.
            $perDesign = [];
            $typeForDesign = [];
            foreach ($due as $order) {
                $perDesign[$order->ship_design_id] = ($perDesign[$order->ship_design_id] ?? 0) + 1;
                $typeForDesign[$order->ship_design_id] = $order->aircraft_type_id;
            }

            foreach ($perDesign as $designId => $count) {
                $row = Aircraft::firstOrNew([
                    'user_id' => $user->id,
                    'ship_design_id' => $designId,
                ]);
                $row->aircraft_type_id = $typeForDesign[$designId] ?? $row->aircraft_type_id;
                $row->quantity = (int) $row->quantity + $count;
                $row->save();
                $completed += $count;
            }

            AircraftBuildOrder::whereIn('id', $due->pluck('id'))->delete();
        });

        return $completed;
    }

    /**
     * The timestamp at which the given slot's queue is currently free, i.e.
     * the max finishes_at among that slot's pending orders (or $now if empty).
     */
    public function slotFreeAt(User $user, int $slot, CarbonInterface $now): CarbonInterface
    {
        $last = AircraftBuildOrder::where('user_id', $user->id)
            ->where('slot', $slot)
            ->max('finishes_at');

        $lastAt = $last ? \Illuminate\Support\Carbon::parse($last) : $now;

        return $lastAt->greaterThan($now) ? $lastAt : $now;
    }

    /**
     * Pick the slot that will become free the soonest (shortest queue),
     * within the number of slots the hangar provides.
     */
    public function nextSlot(User $user, CarbonInterface $now): ?int
    {
        $slots = $this->buildSlots($user);
        if ($slots <= 0) {
            return null;
        }

        $best = null;
        $bestAt = null;
        for ($slot = 0; $slot < $slots; $slot++) {
            $freeAt = $this->slotFreeAt($user, $slot, $now);
            if ($bestAt === null || $freeAt->lessThan($bestAt)) {
                $bestAt = $freeAt;
                $best = $slot;
            }
        }

        return $best;
    }
}
