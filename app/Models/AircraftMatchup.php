<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AircraftMatchup extends Model
{
    protected $fillable = [
        'attacker_class',
        'defender_class',
        'damage_bonus_percent',
        'damage_reduction_percent',
    ];

    protected $casts = [
        'damage_bonus_percent' => 'integer',
        'damage_reduction_percent' => 'integer',
    ];
}
