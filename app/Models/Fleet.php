<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fleet extends Model
{
    /** Composition limits. */
    public const MAX_SLOTS = 12;
    public const MAX_PER_SLOT = 5000;

    /** Marker footprint on the planetary map (generic units). */
    public const SIZE = 10;

    protected $fillable = [
        'user_id',
        'commander_id',
        'name',
        'x',
        'y',
    ];

    protected $casts = [
        'x' => 'integer',
        'y' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commander(): BelongsTo
    {
        return $this->belongsTo(Commander::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(FleetSlot::class);
    }
}
