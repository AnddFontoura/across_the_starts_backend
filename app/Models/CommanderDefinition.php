<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommanderDefinition extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'color',
        'image_url',
        'is_recruitable',
        'max_rank',
        'natural_growth_per_level',
        'base_pontaria',
        'base_desvio',
        'base_critico',
        'base_velocidade',
    ];

    protected $casts = [
        'is_recruitable' => 'boolean',
        'max_rank' => 'integer',
        'natural_growth_per_level' => 'integer',
        'base_pontaria' => 'integer',
        'base_desvio' => 'integer',
        'base_critico' => 'integer',
        'base_velocidade' => 'integer',
    ];

    public function commanders(): HasMany
    {
        return $this->hasMany(Commander::class);
    }
}
