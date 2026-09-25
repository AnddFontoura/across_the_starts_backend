<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipDesignModule extends Model
{
    protected $fillable = [
        'ship_design_id',
        'module_type_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function design(): BelongsTo
    {
        return $this->belongsTo(ShipDesign::class, 'ship_design_id');
    }

    public function moduleType(): BelongsTo
    {
        return $this->belongsTo(ModuleType::class, 'module_type_id');
    }
}
