<?php

namespace App\Models;

use App\Services\StructureLevelCalculator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Structure extends Model
{
    protected $fillable = [
        'base_id',
        'structure_type_id',
        'level',
        'x',
        'y',
        'last_collected_at',
        'is_constructed',
        'busy_until',
        'pending_level',
        'current_hp',
    ];

    protected $casts = [
        'level' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
        'last_collected_at' => 'datetime',
        'is_constructed' => 'boolean',
        'busy_until' => 'datetime',
        'pending_level' => 'integer',
        'current_hp' => 'integer',
    ];

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(StructureType::class, 'structure_type_id');
    }

    protected function calculator(): StructureLevelCalculator
    {
        return app(StructureLevelCalculator::class);
    }

    /**
     * Production per minute at the current level.
     */
    public function productionPerMinute(): int
    {
        return $this->calculator()->productionPerMinute($this->type, $this->level);
    }

    /**
     * Maximum amount this structure can accumulate at its current level.
     * Production never exceeds this ceiling until collected.
     */
    public function maxCapacity(): int
    {
        return $this->calculator()->maxCapacity($this->type, $this->level);
    }

    /**
     * Amount of EACH resource kept safe by this structure (storage only).
     */
    public function protection(): int
    {
        // No protection while the structure is still being built.
        if (! $this->type->isStorage() || ! $this->is_constructed) {
            return 0;
        }

        return $this->calculator()->protection($this->type, $this->level);
    }

    /**
     * Maximum hit points at the current level (0 for types without HP).
     */
    public function maxHp(): int
    {
        return $this->calculator()->hp($this->type, $this->level);
    }

    /**
     * Current hit points. Defaults to the level's max HP when unset ("full").
     */
    public function currentHp(): int
    {
        $max = $this->maxHp();

        if ($this->current_hp === null) {
            return $max;
        }

        return min((int) $this->current_hp, $max);
    }

    /**
     * Damage dealt per shot at the current level (defense structures only,
     * once fully constructed).
     */
    public function damage(): int
    {
        if (! $this->type->isDefense() || ! $this->is_constructed) {
            return 0;
        }

        return $this->calculator()->damage($this->type, $this->level);
    }

    /**
     * Total resources invested in this structure so far, per resource. Includes
     * an in-progress upgrade (its cost was already debited when it started).
     *
     * @return array{gold:int, metal:int, energy:int}
     */
    public function totalInvested(): array
    {
        // If an upgrade is in progress, its cost is already spent, so count up
        // to the pending level; otherwise up to the current level.
        $effectiveLevel = $this->pending_level ?? $this->level;

        return $this->calculator()->totalInvested($this->type, $effectiveLevel);
    }

    /**
     * Resources refunded when demolishing (50% of the total invested).
     *
     * @return array{gold:int, metal:int, energy:int}
     */
    public function demolitionRefund(): array
    {
        $invested = $this->totalInvested();

        return [
            'gold' => intdiv($invested['gold'], 2),
            'metal' => intdiv($invested['metal'], 2),
            'energy' => intdiv($invested['energy'], 2),
        ];
    }

    /**
     * Construction slots this structure grants to the base (command only,
     * and only once fully constructed).
     */
    public function structureSlots(): int
    {
        if (! $this->type->isCommand() || ! $this->is_constructed) {
            return 0;
        }

        return $this->calculator()->structureSlots($this->type, $this->level);
    }

    /**
     * Cost to upgrade to the next level as {gold, metal, energy},
     * or null when at max level.
     *
     * @return array{gold:int, metal:int, energy:int}|null
     */
    public function upgradeCost(): ?array
    {
        return $this->calculator()->upgradeCost($this->type, $this->level);
    }

    public function isMaxLevel(): bool
    {
        return $this->level >= $this->type->max_level;
    }

    /**
     * Time (in seconds) to upgrade to the next level, or null at max level.
     */
    public function upgradeTime(): ?int
    {
        return $this->calculator()->upgradeTime($this->type, $this->level);
    }

    /**
     * Whether a build or upgrade is currently in progress (not yet due).
     */
    public function isBusy(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $this->busy_until !== null && $this->busy_until->greaterThan($now);
    }

    /**
     * Seconds remaining on the current build/upgrade (0 when idle/done).
     */
    public function remainingSeconds(?CarbonInterface $now = null): int
    {
        $now ??= now();

        if ($this->busy_until === null || $this->busy_until->lessThanOrEqualTo($now)) {
            return 0;
        }

        return (int) ceil($now->diffInSeconds($this->busy_until, false));
    }

    /**
     * Finalize a build/upgrade whose timer has elapsed. Idempotent and lazy:
     * called whenever the base is read or acted upon, so we don't need a worker.
     *
     * Returns true if state changed (and was persisted).
     */
    public function settle(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        if ($this->busy_until === null || $this->busy_until->greaterThan($now)) {
            return false;
        }

        $changed = false;

        // Finish an in-progress upgrade.
        if ($this->pending_level !== null) {
            $this->level = $this->pending_level;
            $this->pending_level = null;
            // Reset the production timer so counting starts at the new level.
            $this->last_collected_at = $this->busy_until;
            $changed = true;
        }

        // Finish the initial construction.
        if (! $this->is_constructed) {
            $this->is_constructed = true;
            // Production starts counting when construction completes.
            $this->last_collected_at = $this->busy_until;
            $changed = true;
        }

        $this->busy_until = null;

        if ($changed) {
            $this->save();
        }

        return $changed;
    }

    /**
     * Whole minutes elapsed since the last collection.
     */
    public function elapsedMinutes(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $since = $this->last_collected_at ?? $this->created_at ?? $now;

        return max(0, (int) floor($since->diffInMinutes($now)));
    }

    /**
     * Amount of resource accumulated since the last collection, capped at the
     * structure's maximum capacity for its current level.
     */
    public function pendingProduction(?CarbonInterface $now = null): int
    {
        // Storage structures don't produce; neither do unfinished builds.
        if (! $this->type->isProducer() || ! $this->is_constructed) {
            return 0;
        }

        $minutes = $this->elapsedMinutes($now);
        if ($minutes <= 0) {
            return 0;
        }

        $produced = $minutes * $this->productionPerMinute();

        return min($produced, $this->maxCapacity());
    }
}
