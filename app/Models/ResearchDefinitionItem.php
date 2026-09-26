<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inventory item consumed to start a given research (referenced by the
 * item's stable catalog key so seeders don't depend on autoincrement ids).
 */
class ResearchDefinitionItem extends Model
{
    protected $fillable = [
        'research_definition_id',
        'item_key',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ResearchDefinition::class, 'research_definition_id');
    }

    /**
     * The catalog item this requirement points at (by key), if it exists.
     */
    public function item(): ?Item
    {
        return Item::where('key', $this->item_key)->first();
    }
}
