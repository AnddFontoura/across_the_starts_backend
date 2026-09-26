<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One atomic action in a battle, ordered by `sequence` and grouped by `round`.
 * The frontend replays these to animate the fight.
 */
class BattleEvent extends Model
{
    public const TYPE_ROUND_START = 'round_start';
    public const TYPE_ROUND_END = 'round_end';
    public const TYPE_MOVE = 'move';
    public const TYPE_ATTACK = 'attack';
    public const TYPE_DESTROY = 'destroy';
    public const TYPE_ENERGY = 'energy';
    public const TYPE_STRANDED_LOST = 'stranded_lost';
    public const TYPE_BATTLE_END = 'battle_end';

    protected $fillable = [
        'battle_instance_id',
        'round',
        'sequence',
        'type',
        'actor_fleet_id',
        'target_fleet_id',
        'payload',
    ];

    protected $casts = [
        'round' => 'integer',
        'sequence' => 'integer',
        'payload' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(BattleInstance::class, 'battle_instance_id');
    }
}
