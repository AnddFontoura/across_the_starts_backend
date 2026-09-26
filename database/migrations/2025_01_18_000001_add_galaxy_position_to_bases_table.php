<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every player planet (the terrestrial base) now lives at a fixed spot in
     * the galaxy: one of 30 quadrants, and one of up to 1000 slots inside it.
     *
     *   quadrant : 1..30
     *   slot     : 0..999  (position within the quadrant grid)
     *
     * Planetary (orbital) bases are not placed in the galaxy — they belong to
     * the same planet as their owner's terrestrial base, so their columns stay
     * null.
     */
    public const QUADRANTS = 30;

    public const SLOTS_PER_QUADRANT = 1000;

    public function up(): void
    {
        Schema::table('bases', function (Blueprint $table) {
            $table->unsignedTinyInteger('quadrant')->nullable()->after('kind');
            $table->unsignedSmallInteger('slot')->nullable()->after('quadrant');

            // At most one planet per (quadrant, slot).
            $table->unique(['quadrant', 'slot']);
            $table->index('quadrant');
        });

        // Backfill: scatter existing terrestrial bases across the galaxy so old
        // accounts also appear on the map. We assign sequentially into random
        // slots, respecting the 1000-per-quadrant capacity.
        $this->backfillExisting();
    }

    public function down(): void
    {
        Schema::table('bases', function (Blueprint $table) {
            $table->dropUnique(['quadrant', 'slot']);
            $table->dropIndex(['quadrant']);
            $table->dropColumn(['quadrant', 'slot']);
        });
    }

    /**
     * Place every existing terrestrial base that has no position yet. Uses a
     * simple in-memory tracker of taken slots per quadrant to avoid collisions.
     */
    protected function backfillExisting(): void
    {
        $bases = DB::table('bases')
            ->where('kind', 'terrestrial')
            ->whereNull('quadrant')
            ->orderBy('id')
            ->get(['id']);

        if ($bases->isEmpty()) {
            return;
        }

        // taken[quadrant] = array of used slots.
        $taken = [];

        foreach ($bases as $base) {
            $placement = $this->pickFreeSlot($taken);

            if ($placement === null) {
                // Galaxy is full (30k planets). Leave unplaced rather than fail.
                continue;
            }

            [$quadrant, $slot] = $placement;
            $taken[$quadrant][$slot] = true;

            DB::table('bases')
                ->where('id', $base->id)
                ->update(['quadrant' => $quadrant, 'slot' => $slot]);
        }
    }

    /**
     * Pick a random (quadrant, slot) that isn't already taken in $taken.
     * Returns [quadrant, slot] or null when the whole galaxy is full.
     */
    protected function pickFreeSlot(array $taken): ?array
    {
        // Quadrants that still have a free slot.
        $available = [];
        for ($q = 1; $q <= self::QUADRANTS; $q++) {
            if (count($taken[$q] ?? []) < self::SLOTS_PER_QUADRANT) {
                $available[] = $q;
            }
        }

        if (empty($available)) {
            return null;
        }

        $quadrant = $available[array_rand($available)];

        // Find a free slot in the chosen quadrant.
        do {
            $slot = random_int(0, self::SLOTS_PER_QUADRANT - 1);
        } while (isset($taken[$quadrant][$slot]));

        return [$quadrant, $slot];
    }
};
