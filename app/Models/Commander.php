<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commander extends Model
{
    /** Roman numerals for display (index 1..10). */
    public const ROMAN = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];

    protected $fillable = [
        'user_id',
        'commander_definition_id',
        'name',
        'rank',
        'prof_cruiser',
        'prof_battleship',
        'prof_frigate',
        'prof_fighter',
        'prof_machinegun',
        'prof_laser',
        'prof_missile',
    ];

    protected $casts = [
        'rank' => 'integer',
        'prof_cruiser' => 'integer',
        'prof_battleship' => 'integer',
        'prof_frigate' => 'integer',
        'prof_fighter' => 'integer',
        'prof_machinegun' => 'integer',
        'prof_laser' => 'integer',
        'prof_missile' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CommanderDefinition::class, 'commander_definition_id');
    }

    /** Proficiency level (1..5) for a ship class key. */
    public function classProficiency(string $class): int
    {
        return (int) ($this->{'prof_'.$class} ?? 1);
    }

    /** Proficiency level (1..5) for a weapon attack_type. */
    public function weaponProficiency(string $attackType): int
    {
        return (int) ($this->{'prof_'.$attackType} ?? 1);
    }
}
