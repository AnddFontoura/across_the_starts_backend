<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalog item: something a player can hold in the "Forte Protetor" inventory.
 *
 * Two types for now:
 *  - consumable: single-use effects (stackable up to max_stack).
 *  - plant: blueprints / schematics used to research new ships and weapons.
 *
 * Per-item effects are stored as JSON for flexibility, and max_stack bounds how
 * many can pile up in a single inventory slot (1 = unique, e.g. 9999 = common).
 */
class Item extends Model
{
    public const TYPE_CONSUMABLE = 'consumable';

    public const TYPE_PLANT = 'plant';

    protected $fillable = [
        'key',
        'name',
        'description',
        'type',
        'effects',
        'max_stack',
        'icon',
        'color',
    ];

    protected $casts = [
        'effects' => 'array',
        'max_stack' => 'integer',
    ];

    public function playerItems(): HasMany
    {
        return $this->hasMany(PlayerItem::class);
    }

    public function isConsumable(): bool
    {
        return $this->type === self::TYPE_CONSUMABLE;
    }

    public function isPlant(): bool
    {
        return $this->type === self::TYPE_PLANT;
    }
}
