<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankingBonus extends Model
{
    public const SCOPE_PROFICIENCY = 'proficiency';
    public const SCOPE_COMMANDER = 'commander';

    protected $fillable = [
        'scope',
        'level',
        'attack_percent',
        'defense_percent',
    ];

    protected $casts = [
        'level' => 'integer',
        'attack_percent' => 'integer',
        'defense_percent' => 'integer',
    ];
}
