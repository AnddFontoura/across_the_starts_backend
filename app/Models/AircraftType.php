<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AircraftType extends Model
{
    /** The four aircraft classes. Many ships exist per class. */
    public const CLASSES = ['cruiser', 'battleship', 'frigate', 'fighter'];

    /** Human labels for the classes (pt-BR). */
    public const CLASS_LABELS = [
        'cruiser' => 'Cruzador',
        'battleship' => 'Encouraçado',
        'frigate' => 'Fragata',
        'fighter' => 'Caça',
    ];

    protected $fillable = [
        'key',
        'class',
        'name',
        'description',
        'color',
        'image_url',
        'storage',
        'cost_gold',
        'cost_metal',
        'cost_energy',
        'build_time',
        'shield',
        'hull',
        'movement',
        'energy_capacity',
        'energy_upkeep',
        'special_attributes',
    ];

    protected $casts = [
        'storage' => 'integer',
        'cost_gold' => 'integer',
        'cost_metal' => 'integer',
        'cost_energy' => 'integer',
        'build_time' => 'integer',
        'shield' => 'integer',
        'hull' => 'integer',
        'movement' => 'integer',
        'energy_capacity' => 'integer',
        'energy_upkeep' => 'integer',
        'special_attributes' => 'array',
    ];

    /**
     * Matchup rows where this type is the attacker.
     */
    public function attackMatchups(): HasMany
    {
        return $this->hasMany(AircraftMatchup::class, 'attacker_type_id');
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
