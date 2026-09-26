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
        'scope',
        'production_per_hour',
        'color',
        'max_level',
        'production_base',
        'production_growth',
        'capacity_base',
        'capacity_growth',
        'protection_base',
        'protection_growth',
        'hp_base',
        'hp_growth',
        'damage_base',
        'damage_growth',
        'range_base',
        'range_growth',
        'build_time_reduction_base',
        'build_time_reduction_growth',
        'build_slots_base',
        'build_slots_growth',
        'build_slots_tiers',
        'fleet_capacity_base',
        'fleet_capacity_growth',
        'inventory_slots_base',
        'inventory_slots_per_level',
        'research_time_reduction_base',
        'research_time_reduction_per_level',
        'research_time_reduction_cap',
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
        'hp_base' => 'integer',
        'hp_growth' => 'float',
        'damage_base' => 'integer',
        'damage_growth' => 'float',
        'range_base' => 'integer',
        'range_growth' => 'float',
        'build_time_reduction_base' => 'integer',
        'build_time_reduction_growth' => 'float',
        'build_slots_base' => 'integer',
        'build_slots_growth' => 'float',
        'build_slots_tiers' => 'array',
        'fleet_capacity_base' => 'integer',
        'fleet_capacity_growth' => 'float',
        'inventory_slots_base' => 'integer',
        'inventory_slots_per_level' => 'integer',
        'research_time_reduction_base' => 'integer',
        'research_time_reduction_per_level' => 'integer',
        'research_time_reduction_cap' => 'integer',
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

    /**
     * A defense structure (planetary): deals damage to invaders and has HP.
     */
    public function isDefense(): bool
    {
        return $this->category === 'defense';
    }

    /**
     * A support structure (e.g. the Aircraft Hangar): provides a passive bonus
     * such as reducing aircraft build time. Doesn't produce, store or attack.
     */
    public function isSupport(): bool
    {
        return $this->category === 'support';
    }

    /**
     * An inventory structure (e.g. the "Forte Protetor"): the planet owner's
     * item box. Grants the player a number of inventory slots that scales with
     * level. Doesn't produce, store resources or attack.
     */
    public function isInventory(): bool
    {
        return $this->category === 'inventory';
    }

    /**
     * A research structure (the "Centro de Pesquisa"): unlocks the research
     * panel and reduces research wait time as its level rises. Doesn't produce,
     * store resources or attack.
     */
    public function isResearch(): bool
    {
        return $this->category === 'research';
    }

    public function isTerrestrial(): bool
    {
        return $this->scope !== 'planetary';
    }

    public function isPlanetary(): bool
    {
        return $this->scope === 'planetary';
    }
}
