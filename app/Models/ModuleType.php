<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleType extends Model
{
    /** Weapon attack types. */
    public const WEAPON_TYPES = ['machinegun', 'laser', 'missile'];

    protected $fillable = [
        'key',
        'name',
        'description',
        'color',
        'image_url',
        'movement',
        'attack',
        'hull',
        'shield',
        'space',
        'attack_type',
        'range',
        'energy_capacity',
        'energy_upkeep',
        'build_time_add',
        'cost_gold',
        'cost_metal',
        'cost_energy',
        'special_attributes',
    ];

    protected $casts = [
        'movement' => 'integer',
        'attack' => 'integer',
        'hull' => 'integer',
        'shield' => 'integer',
        'space' => 'integer',
        'range' => 'integer',
        'energy_capacity' => 'integer',
        'energy_upkeep' => 'integer',
        'build_time_add' => 'integer',
        'cost_gold' => 'integer',
        'cost_metal' => 'integer',
        'cost_energy' => 'integer',
        'special_attributes' => 'array',
    ];

    /**
     * Whether this module is a weapon (has an attack type).
     */
    public function isWeapon(): bool
    {
        return $this->attack_type !== null && in_array($this->attack_type, self::WEAPON_TYPES, true);
    }

    /**
     * Build cost as a {gold, metal, energy} array.
     *
     * @return array{gold:int, metal:int, energy:int}
     */
    public function cost(): array
    {
        return [
            'gold' => (int) $this->cost_gold,
            'metal' => (int) $this->cost_metal,
            'energy' => (int) $this->cost_energy,
        ];
    }
}
