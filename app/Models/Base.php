<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Base extends Model
{
    protected $fillable = [
        'user_id',
        'width',
        'height',
        'gold',
        'metal',
        'energy',
        'total_gold_collected',
        'total_metal_collected',
        'total_energy_collected',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'gold' => 'integer',
        'metal' => 'integer',
        'energy' => 'integer',
        'total_gold_collected' => 'integer',
        'total_metal_collected' => 'integer',
        'total_energy_collected' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function structures(): HasMany
    {
        return $this->hasMany(Structure::class);
    }

    /**
     * Whether the given rectangle fits fully inside the terrain bounds.
     */
    public function fitsInBounds(int $x, int $y, int $width, int $height): bool
    {
        return $x >= 0
            && $y >= 0
            && ($x + $width) <= $this->width
            && ($y + $height) <= $this->height;
    }

    /**
     * Whether a rectangle would overlap any existing structure.
     * Two axis-aligned rectangles do NOT overlap when one is entirely
     * to the left/right/above/below the other.
     *
     * @param  int|null  $ignoreStructureId  structure to exclude (when moving)
     */
    public function hasOverlap(int $x, int $y, int $width, int $height, ?int $ignoreStructureId = null): bool
    {
        foreach ($this->structures()->with('type')->get() as $structure) {
            if ($ignoreStructureId !== null && $structure->id === $ignoreStructureId) {
                continue;
            }

            $ex = $structure->x;
            $ey = $structure->y;
            $ew = $structure->type->width;
            $eh = $structure->type->height;

            $separated = ($x + $width) <= $ex
                || $ex + $ew <= $x
                || ($y + $height) <= $ey
                || $ey + $eh <= $y;

            if (! $separated) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add collected resource to the current balance and to the lifetime total.
     */
    public function creditResource(string $resource, int $amount): void
    {
        if ($amount <= 0 || ! in_array($resource, ['gold', 'metal', 'energy'], true)) {
            return;
        }

        $this->increment($resource, $amount);
        $this->increment('total_'.$resource.'_collected', $amount);
    }

    /**
     * Total protection provided by all storage structures, per resource.
     * (Currently the same value applies to each of the three resources.)
     */
    public function totalProtection(): int
    {
        return $this->structures
            ->filter(fn ($s) => $s->type->isStorage())
            ->sum(fn ($s) => $s->protection());
    }
}
