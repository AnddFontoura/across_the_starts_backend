<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stack of ships in an enemy fleet, built from a base aircraft_type plus a
 * module loadout snapshot ([{module_type_id, quantity}, ...]). Independent of
 * any player's ship designs.
 */
class InvestigationEnemySlot extends Model
{
    protected $fillable = [
        'investigation_enemy_fleet_id',
        'aircraft_type_id',
        'name',
        'modules',
        'quantity',
    ];

    protected $casts = [
        'modules' => 'array',
        'quantity' => 'integer',
    ];

    public function enemyFleet(): BelongsTo
    {
        return $this->belongsTo(InvestigationEnemyFleet::class, 'investigation_enemy_fleet_id');
    }

    public function baseType(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class, 'aircraft_type_id');
    }
}
