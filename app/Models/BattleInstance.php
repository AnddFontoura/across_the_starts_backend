<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A live run of an investigation for one player. Server-authoritative; advances
 * one round at a time. See BattleService for the resolution logic.
 */
class BattleInstance extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ABANDONED = 'abandoned';

    public const SIDE_PLAYER = 'player';
    public const SIDE_ENEMY = 'enemy';

    protected $fillable = [
        'user_id',
        'investigation_definition_id',
        'status',
        'current_round',
        'max_rounds',
        'map_width',
        'map_height',
        'seed',
        'settled',
        'rewards_claimed',
        'finished_at',
    ];

    protected $casts = [
        'current_round' => 'integer',
        'max_rounds' => 'integer',
        'map_width' => 'integer',
        'map_height' => 'integer',
        'seed' => 'integer',
        'settled' => 'boolean',
        'rewards_claimed' => 'boolean',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(InvestigationDefinition::class, 'investigation_definition_id');
    }

    public function fleets(): HasMany
    {
        return $this->hasMany(BattleFleet::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BattleEvent::class);
    }

    public function isOver(): bool
    {
        return $this->status !== self::STATUS_IN_PROGRESS;
    }
}
