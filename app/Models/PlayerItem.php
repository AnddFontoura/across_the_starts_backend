<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's ownership of an item. One row per (user, item): a single stack
 * occupying one inventory slot. The quantity is bounded by the item's
 * max_stack; the number of rows is bounded by the Forte Protetor's slots.
 */
class PlayerItem extends Model
{
    protected $fillable = [
        'user_id',
        'item_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
