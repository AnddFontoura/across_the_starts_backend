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

    /**
     * Battle-fleet rows referencing this fleet. A fleet is "in battle" (locked)
     * while any of these belongs to an in-progress instance.
     */
    public function battleFleets(): HasMany
    {
        return $this->hasMany(BattleFleet::class);
    }

    /**
     * Whether this fleet is currently committed to an in-progress battle
     * instance (and therefore locked out of the base and uneditable).
     */
    public function isInBattle(): bool
    {
        return $this->battleFleets()
            ->whereHas('instance', fn ($q) => $q->where('status', BattleInstance::STATUS_IN_PROGRESS))
            ->exists();
    }

    /**
     * Scope: only fleets NOT committed to an in-progress battle. Used to hide
     * dispatched fleets from the base map and the fleet manager while locked.
     */
    public function scopeNotInBattle($query)
    {
        return $query->whereDoesntHave('battleFleets.instance', function ($q) {
            $q->where('status', BattleInstance::STATUS_IN_PROGRESS);
        });
    }
}
