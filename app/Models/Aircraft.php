<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aircraft extends Model
{
    protected $table = 'aircraft';

    protected $fillable = [
        'user_id',
        'ship_design_id',
        'aircraft_type_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
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
