<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A predefined enemy fleet within an investigation. Self-contained: a name, a
 * fixed spawn position, and NPC commander proxy stats (velocidade for
 * initiative, flat attack/defense percentages). Composition lives in slots.
 */
class InvestigationEnemyFleet extends Model
{
    protected $fillable = [
        'investigation_definition_id',
        'name',
        'start_x',
        'start_y',
        'commander_velocidade',
        'attack_percent',
        'defense_percent',
    ];

    protected $casts = [
        'start_x' => 'integer',
        'start_y' => 'integer',
        'commander_velocidade' => 'integer',
        'attack_percent' => 'integer',
        'defense_percent' => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(InvestigationDefinition::class, 'investigation_definition_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(InvestigationEnemySlot::class);
    }
}
