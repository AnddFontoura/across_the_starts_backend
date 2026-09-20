<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StructureType extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'width',
        'height',
        'resource',
        'category',
        'production_per_hour',
        'color',
        'max_level',
        'production_base',
        'production_growth',
        'capacity_base',
        'capacity_growth',
        'protection_base',
        'protection_growth',
        'upgrade_cost_gold_base',
        'upgrade_cost_gold_growth',
        'upgrade_cost_metal_base',
        'upgrade_cost_metal_growth',
        'upgrade_cost_energy_base',
        'upgrade_cost_energy_growth',
        'build_time',
        'upgrade_time_base',
        'upgrade_time_growth',
        'is_unique',
        'structure_slots_base',
        'structure_slots_growth',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'production_per_hour' => 'integer',
        'max_level' => 'integer',
        'production_base' => 'integer',
        'production_growth' => 'float',
        'capacity_base' => 'integer',
        'capacity_growth' => 'float',
        'protection_base' => 'integer',
        'protection_growth' => 'float',
        'upgrade_cost_gold_base' => 'integer',
        'upgrade_cost_gold_growth' => 'float',
        'upgrade_cost_metal_base' => 'integer',
        'upgrade_cost_metal_growth' => 'float',
        'upgrade_cost_energy_base' => 'integer',
        'upgrade_cost_energy_growth' => 'float',
        'build_time' => 'integer',
        'upgrade_time_base' => 'integer',
        'upgrade_time_growth' => 'float',
        'is_unique' => 'boolean',
        'structure_slots_base' => 'integer',
        'structure_slots_growth' => 'float',
    ];

    public function structures(): HasMany
    {
        return $this->hasMany(Structure::class);
    }

    public function levelConfigs(): HasMany
    {
        return $this->hasMany(StructureLevelConfig::class);
    }

    public function isStorage(): bool
    {
        return $this->category === 'storage';
    }

    public function isProducer(): bool
    {
        return $this->category === 'producer';
    }

    public function isCommand(): bool
    {
        return $this->category === 'command';
    }
}
