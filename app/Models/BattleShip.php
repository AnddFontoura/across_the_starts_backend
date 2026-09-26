<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stack of identical ships in a battle fleet. Carries per-ship combat stats
 * (bonuses already folded in) plus the pooled hp of the surviving ships, so
 * damage removes whole ships as it accumulates. Player-side stacks keep a
 * ship_design_id so end-of-battle settlement decrements the right Aircraft row.
 */
class BattleShip extends Model
{
    protected $fillable = [
        'battle_fleet_id',
        'ship_design_id',
        'aircraft_type_id',
        'name',
        'ship_class',
        'weapon_type',
        'attack',
        'hull',
        'shield',
        'weapon_range',
        'movement',
        'quantity',
        'quantity_remaining',
        'hp_remaining',
    ];

    protected $casts = [
        'attack' => 'integer',
        'hull' => 'integer',
        'shield' => 'integer',
        'weapon_range' => 'integer',
        'movement' => 'integer',
        'quantity' => 'integer',
        'quantity_remaining' => 'integer',
        'hp_remaining' => 'integer',
    ];

    public function battleFleet(): BelongsTo
    {
        return $this->belongsTo(BattleFleet::class);
    }

    /** Hp of a single ship in this stack (shield + hull). */
    public function perShipHp(): int
    {
        return max(1, (int) $this->shield + (int) $this->hull);
    }
}
