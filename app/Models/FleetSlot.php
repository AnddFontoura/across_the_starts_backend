<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetSlot extends Model
{
    protected $fillable = [
        'fleet_id',
        'ship_design_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(ShipDesign::class, 'ship_design_id');
    }
}
