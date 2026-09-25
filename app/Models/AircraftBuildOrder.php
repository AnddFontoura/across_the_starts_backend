<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AircraftBuildOrder extends Model
{
    protected $fillable = [
        'user_id',
        'ship_design_id',
        'aircraft_type_id',
        'slot',
        'finishes_at',
    ];

    protected $casts = [
        'slot' => 'integer',
        'finishes_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class, 'aircraft_type_id');
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(ShipDesign::class, 'ship_design_id');
    }
}
