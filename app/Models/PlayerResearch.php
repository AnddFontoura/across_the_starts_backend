<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's progress on a single research. `level` is the completed level
 * (0 = not researched yet). An in-progress research is tracked with
 * target_level / started_at / finish_at. Only one research may be in progress
 * per player per lane (1 technology + up to 3 plants), enforced by the
 * ResearchService.
 */
class PlayerResearch extends Model
{
    protected $table = 'player_research';

    protected $fillable = [
        'user_id',
        'research_definition_id',
        'level',
        'target_level',
        'started_at',
        'finish_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'target_level' => 'integer',
        'started_at' => 'datetime',
        'finish_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ResearchDefinition::class, 'research_definition_id');
    }

    /**
     * Whether a research is currently in progress (timer not yet elapsed).
     */
    public function isBusy(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $this->finish_at !== null && $this->finish_at->greaterThan($now);
    }

    /**
     * Seconds remaining on the in-progress research (0 when idle/done).
     */
    public function remainingSeconds(?CarbonInterface $now = null): int
    {
        $now ??= now();

        if ($this->finish_at === null || $this->finish_at->lessThanOrEqualTo($now)) {
            return 0;
        }

        return (int) ceil($now->diffInSeconds($this->finish_at, false));
    }

    /**
     * Finalize an in-progress research whose timer has elapsed. Idempotent and
     * lazy (mirrors Structure::settle()): called on every research read so we
     * don't need a background worker. Returns true if state changed.
     */
    public function settle(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        if ($this->finish_at === null || $this->finish_at->greaterThan($now)) {
            return false;
        }

        if ($this->target_level !== null) {
            $this->level = $this->target_level;
        }

        $this->target_level = null;
        $this->started_at = null;
        $this->finish_at = null;
        $this->save();

        return true;
    }
}
