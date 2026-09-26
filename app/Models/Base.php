<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Base extends Model
{
    protected $fillable = [
        'user_id',
        'kind',
        'quadrant',
        'slot',
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
        'quadrant' => 'integer',
        'slot' => 'integer',
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
     * Whether this is the orbital defense base (vs. the terrestrial resource base).
     */
    public function isPlanetary(): bool
    {
        return $this->kind === 'planetary';
    }

    /**
     * The "scope" of structure types this base can host.
     */
    public function scope(): string
    {
        return $this->isPlanetary() ? 'planetary' : 'terrestrial';
    }

    /**
     * The base that holds the player's shared resource stockpile. Resources
     * live on the terrestrial base; the planetary base spends from the same
     * wallet. For the terrestrial base this is itself.
     */
    public function wallet(): Base
    {
        if (! $this->isPlanetary()) {
            return $this;
        }

        return static::firstOrCreate([
            'user_id' => $this->user_id,
            'kind' => 'terrestrial',
        ]);
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
     * Add resource to the balance WITHOUT counting toward lifetime totals.
     * Used for refunds (demolition), which aren't "collected" production.
     */
    public function creditResourceRaw(string $resource, int $amount): void
    {
        if ($amount <= 0 || ! in_array($resource, ['gold', 'metal', 'energy'], true)) {
            return;
        }

        $this->increment($resource, $amount);
    }

    /**
     * Total protection provided by all storage structures, per resource.
     * (Currently the same value applies to each of the three resources.)
     */
    public function totalProtection(): int
    {
        $fromStructures = $this->structures
            ->filter(fn ($s) => $s->type->isStorage())
            ->sum(fn ($s) => $s->protection());

        // Absolute protection bonus from the owner's completed research
        // (e.g. the "warehouse protection" technology: +1,000,000 per level).
        $fromResearch = app(\App\Services\ResearchBonusService::class)
            ->warehouseProtectionFlat($this->user);

        return (int) $fromStructures + (int) $fromResearch;
    }

    /**
     * The Command Center structure of this base, if any.
     */
    public function commandStructure(): ?Structure
    {
        return $this->structures->first(fn ($s) => $s->type->isCommand());
    }

    /**
     * Current level of the (constructed) Command Center. Returns 0 when there
     * is no command structure or it's still under construction. This is the
     * cap that other structures can be upgraded to.
     */
    public function commandLevel(): int
    {
        $command = $this->commandStructure();

        if (! $command || ! $command->is_constructed) {
            return 0;
        }

        return $command->level;
    }

    /**
     * Effective maximum number of structures allowed on this base: the global
     * base limit plus the construction slots granted by the Command Center.
     */
    public function effectiveMaxStructures(): int
    {
        $baseLimit = (int) GameSetting::get('max_structures_per_base', 20);

        $bonus = $this->structures->sum(fn ($s) => $s->structureSlots());

        return $baseLimit + $bonus;
    }

    /**
     * The command level that governs quantity-based rules on the planetary
     * base. The Defense Center may reach level 30, but for quantity rules it is
     * treated as capped at 15 (configurable). Returns 0 when there is no built
     * Defense Center.
     */
    public function quantityCommandLevel(): int
    {
        $level = $this->commandLevel();

        if ($level <= 0) {
            return 0;
        }

        $cap = (int) GameSetting::get('defense_center_quantity_cap', 15);

        return min($level, $cap);
    }

    /**
     * Maximum number of a given defense type allowed on the planetary base.
     *
     * - Blocks, artillery and plasma cannons: 3 per (capped) Defense Center level.
     * - Cosmic Ray: 1 per 3 (capped) Defense Center levels.
     *
     * Returns 0 while there is no built Defense Center. Returns null for keys
     * that aren't quantity-limited this way.
     */
    public function maxForDefenseKey(string $key): ?int
    {
        $level = $this->quantityCommandLevel();

        $perLevel = [
            'defense_block',
            'artillery',
            'plasma_cannon',
        ];

        if (in_array($key, $perLevel, true)) {
            $per = (int) GameSetting::get('defense_units_per_center_level', 3);

            return $level * $per;
        }

        if ($key === 'cosmic_ray') {
            $every = (int) GameSetting::get('cosmic_ray_center_levels', 3);

            return $every > 0 ? intdiv($level, $every) : 0;
        }

        return null;
    }

    /**
     * How many structures of a specific type key currently exist on this base
     * (including any still under construction).
     */
    public function countForTypeKey(string $key): int
    {
        return $this->structures
            ->filter(fn ($s) => $s->type->key === $key)
            ->count();
    }

    /**
     * Maximum number of structures allowed for a whole category, configurable
     * via game settings. Producers are NOT limited per-category (they are
     * limited per-type via maxForProducerType); null means no category limit.
     */
    public function maxForCategory(string $category): ?int
    {
        return match ($category) {
            'command' => (int) GameSetting::get('max_command_per_base', 1),
            'storage' => (int) GameSetting::get('max_storage_per_base', 1),
            'inventory' => (int) GameSetting::get('max_inventory_per_base', 1),
            default => null,
        };
    }

    /**
     * Maximum number of producer structures allowed PER TYPE (e.g. up to 10
     * Gold Mines AND up to 10 Metal Mines). Null for non-producer categories.
     */
    public function maxForProducerType(string $category): ?int
    {
        if ($category !== 'producer') {
            return null;
        }

        return (int) GameSetting::get('max_producers_per_type', 10);
    }

    /**
     * How many structures of a given category currently exist on this base
     * (including any that are still under construction).
     */
    public function countForCategory(string $category): int
    {
        return $this->structures
            ->filter(fn ($s) => $s->type->category === $category)
            ->count();
    }

    /**
     * How many structures of a specific type currently exist on this base
     * (including any that are still under construction).
     */
    public function countForType(int $structureTypeId): int
    {
        return $this->structures
            ->filter(fn ($s) => $s->structure_type_id === $structureTypeId)
            ->count();
    }

    /**
     * How many structures are currently under construction or upgrading
     * (their build/upgrade timer hasn't elapsed yet).
     */
    public function busyStructuresCount(): int
    {
        return $this->structures
            ->filter(fn ($s) => $s->isBusy())
            ->count();
    }

    /**
     * Maximum number of simultaneous builds/upgrades allowed on this base.
     * Configurable via game settings.
     */
    public function maxConcurrentBuilds(): int
    {
        return (int) GameSetting::get('max_concurrent_builds', 5);
    }
}
