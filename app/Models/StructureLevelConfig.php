<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StructureLevelConfig extends Model
{
    protected $fillable = [
        'structure_type_id',
        'level',
        'production_per_hour',
        'max_capacity',
        'protection',
        'upgrade_cost_gold',
        'upgrade_cost_metal',
        'upgrade_cost_energy',
        'upgrade_time',
        'hp',
        'damage',
        'range',
    ];

    protected $casts = [
        'level' => 'integer',
        'production_per_hour' => 'integer',
        'max_capacity' => 'integer',
        'protection' => 'integer',
        'upgrade_cost_gold' => 'integer',
        'upgrade_cost_metal' => 'integer',
        'upgrade_cost_energy' => 'integer',
        'upgrade_time' => 'integer',
        'hp' => 'integer',
        'damage' => 'integer',
        'range' => 'integer',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(StructureType::class, 'structure_type_id');
    }
}
