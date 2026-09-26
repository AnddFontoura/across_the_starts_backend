<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item prize for completing an investigation. Prizes are items only; one of
 * them may be a "random resource box" resolved on use. A drop `chance` (1..100)
 * makes prizes guaranteed or random.
 */
class InvestigationPrize extends Model
{
    protected $fillable = [
        'investigation_definition_id',
        'item_id',
        'quantity',
        'chance',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'chance' => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(InvestigationDefinition::class, 'investigation_definition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
