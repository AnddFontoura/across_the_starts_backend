<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-configurable "Investigação Interplanetária" template. Defines the
 * predefined battle a player launches from the operations center: how many of
 * the player's fleets may be sent, the isolated map, the round cap, the win
 * experience, plus the enemy fleets and item prizes (child tables).
 */
class InvestigationDefinition extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'min_player_fleets',
        'max_player_fleets',
        'map_width',
        'map_height',
        'max_rounds',
        'exp_reward',
        'is_active',
        'image_url',
        'color',
    ];

    protected $casts = [
        'min_player_fleets' => 'integer',
        'max_player_fleets' => 'integer',
        'map_width' => 'integer',
        'map_height' => 'integer',
        'max_rounds' => 'integer',
        'exp_reward' => 'integer',
        'is_active' => 'boolean',
    ];

    public function enemyFleets(): HasMany
    {
        return $this->hasMany(InvestigationEnemyFleet::class);
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(InvestigationPrize::class);
    }
}
