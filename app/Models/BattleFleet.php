<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A combatant fleet inside a battle instance. Player fleets reference the
 * source Fleet (locked out of the base for the run); enemy fleets reference the
 * investigation_enemy_fleet they were built from.
 */
class BattleFleet extends Model
{
    protected $fillable = [
        'battle_instance_id',
        'side',
        'fleet_id',
        'commander_id',
        'investigation_enemy_fleet_id',
        'name',
        'velocidade',
        'attack_percent',
        'defense_percent',
        'x',
        'y',
        'movement',
        'alive',
    ];

    protected $casts = [
        'velocidade' => 'integer',
        'attack_percent' => 'integer',
        'defense_percent' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
        'movement' => 'integer',
        'alive' => 'boolean',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(BattleInstance::class, 'battle_instance_id');
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function commander(): BelongsTo
    {
        return $this->belongsTo(Commander::class);
    }

    public function ships(): HasMany
    {
        return $this->hasMany(BattleShip::class);
    }

    public function isPlayer(): bool
    {
        return $this->side === BattleInstance::SIDE_PLAYER;
    }
}
