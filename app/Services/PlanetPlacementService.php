<?php

namespace App\Services;

use App\Models\Base;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Decides where a new planet (terrestrial base) sits in the galaxy.
 *
 * The galaxy is split into a fixed number of quadrants, each holding up to a
 * fixed number of planets. A new planet is dropped into a random quadrant that
 * still has free capacity, at a random free slot within it.
 */
class PlanetPlacementService
{
    /** Number of quadrants in the galaxy. */
    public const QUADRANTS = 30;

    /** Maximum planets a single quadrant can hold. */
    public const SLOTS_PER_QUADRANT = 1000;

    /**
     * Assign a random (quadrant, slot) to the given terrestrial base if it does
     * not already have one. Planetary bases are never placed. Returns the base.
     *
     * Runs inside a transaction with a row-locked count so two simultaneous
     * registrations can't grab the same slot.
     */
    public function place(Base $base): Base
    {
        if ($base->isPlanetary()) {
            return $base;
        }

        if ($base->quadrant !== null && $base->slot !== null) {
            return $base; // already placed
        }

        [$quadrant, $slot] = $this->pickFreeSlot();

        $base->quadrant = $quadrant;
        $base->slot = $slot;
        $base->save();

        return $base;
    }

    /**
     * Pick a random quadrant that still has room, then a random free slot in it.
     *
     * @return array{0:int,1:int}  [quadrant, slot]
     *
     * @throws RuntimeException when the entire galaxy is full.
     */
    public function pickFreeSlot(): array
    {
        // Count planets per quadrant in one query.
        $counts = Base::query()
            ->where('kind', 'terrestrial')
            ->whereNotNull('quadrant')
            ->select('quadrant', DB::raw('count(*) as total'))
            ->groupBy('quadrant')
            ->pluck('total', 'quadrant');

        // Quadrants (1..N) that still have free capacity.
        $available = [];
        for ($q = 1; $q <= self::QUADRANTS; $q++) {
            if ((int) ($counts[$q] ?? 0) < self::SLOTS_PER_QUADRANT) {
                $available[] = $q;
            }
        }

        if (empty($available)) {
            throw new RuntimeException('A galáxia está cheia: não há mais espaço para novos planetas.');
        }

        $quadrant = $available[array_rand($available)];

        $slot = $this->pickFreeSlotInQuadrant($quadrant);

        return [$quadrant, $slot];
    }

    /**
     * Find a free slot (0..SLOTS_PER_QUADRANT-1) within a quadrant.
     *
     * For a nearly-empty quadrant, random probing finds a slot almost
     * immediately. As it fills, we fall back to scanning the set of taken slots
     * and choosing from the remaining ones so we never loop forever.
     */
    protected function pickFreeSlotInQuadrant(int $quadrant): int
    {
        $taken = Base::query()
            ->where('kind', 'terrestrial')
            ->where('quadrant', $quadrant)
            ->whereNotNull('slot')
            ->pluck('slot')
            ->map(fn ($s) => (int) $s)
            ->flip();

        if ($taken->count() >= self::SLOTS_PER_QUADRANT) {
            throw new RuntimeException("O quadrante {$quadrant} está cheio.");
        }

        // Fast path: random probing while the quadrant is not too crowded.
        if ($taken->count() < self::SLOTS_PER_QUADRANT * 0.9) {
            do {
                $slot = random_int(0, self::SLOTS_PER_QUADRANT - 1);
            } while ($taken->has($slot));

            return $slot;
        }

        // Slow path (crowded quadrant): enumerate the free slots and pick one.
        $free = [];
        for ($s = 0; $s < self::SLOTS_PER_QUADRANT; $s++) {
            if (! $taken->has($s)) {
                $free[] = $s;
            }
        }

        return $free[array_rand($free)];
    }
}
