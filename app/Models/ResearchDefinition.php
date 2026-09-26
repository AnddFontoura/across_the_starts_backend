<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A research catalog entry. Two flavours:
 *
 *  - technology: multi-level (max_level >= 1), may depend on other
 *    technologies at a minimum level, costs gold and takes time.
 *  - plant: always a single level. Requires a plant blueprint (item of type
 *    'plant') in the inventory and may consume extra inventory items.
 */
class ResearchDefinition extends Model
{
    public const TYPE_TECHNOLOGY = 'technology';

    public const TYPE_PLANT = 'plant';

    /** Technology areas. Plants leave `area` null. */
    public const AREA_TERRESTRIAL = 'terrestrial';

    public const AREA_AERIAL = 'aerial';

    public const AREA_WEAPONS = 'weapons';

    public const AREAS = [
        self::AREA_TERRESTRIAL,
        self::AREA_AERIAL,
        self::AREA_WEAPONS,
    ];

    public const AREA_LABELS = [
        self::AREA_TERRESTRIAL => 'Base Terrestre',
        self::AREA_AERIAL => 'Aérea',
        self::AREA_WEAPONS => 'Armamentos',
    ];

    protected $fillable = [
        'key',
        'name',
        'description',
        'type',
        'area',
        'effects',
        'gold_cost',
        'research_time',
        'max_level',
        'gold_cost_growth',
        'research_time_growth',
        'required_item_key',
        'consumes_required_item',
        'icon',
        'color',
    ];

    protected $casts = [
        'effects' => 'array',
        'gold_cost' => 'integer',
        'research_time' => 'integer',
        'max_level' => 'integer',
        'gold_cost_growth' => 'float',
        'research_time_growth' => 'float',
        'consumes_required_item' => 'boolean',
    ];

    /**
     * The technologies this research depends on (with a required min level via
     * the pivot). Empty for most plants.
     *
     * @return BelongsToMany<ResearchDefinition>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            ResearchDefinition::class,
            'research_definition_dependencies',
            'research_definition_id',
            'depends_on_id'
        )->withPivot('min_level')->withTimestamps();
    }

    /**
     * Extra inventory items consumed to start this research.
     *
     * @return HasMany<ResearchDefinitionItem>
     */
    public function requiredItems(): HasMany
    {
        return $this->hasMany(ResearchDefinitionItem::class);
    }

    public function isTechnology(): bool
    {
        return $this->type === self::TYPE_TECHNOLOGY;
    }

    public function isPlant(): bool
    {
        return $this->type === self::TYPE_PLANT;
    }

    /**
     * Gold cost to research a given target level (1-indexed). Grows
     * geometrically for technologies; flat for single-level plants.
     */
    public function goldCostForLevel(int $targetLevel): int
    {
        $targetLevel = max(1, $targetLevel);
        $growth = (float) $this->gold_cost_growth;

        if ($growth <= 1.0) {
            return (int) $this->gold_cost;
        }

        return (int) round($this->gold_cost * ($growth ** ($targetLevel - 1)));
    }

    /**
     * Base research time (seconds) for a target level, BEFORE the Research
     * Center's reduction is applied.
     */
    public function baseTimeForLevel(int $targetLevel): int
    {
        $targetLevel = max(1, $targetLevel);
        $growth = (float) $this->research_time_growth;

        if ($growth <= 1.0) {
            return (int) $this->research_time;
        }

        return (int) round($this->research_time * ($growth ** ($targetLevel - 1)));
    }

    /**
     * The gameplay effects declared for this research, as a plain list of
     * associative arrays. Always returns an array (never null).
     *
     * @return array<int, array<string, mixed>>
     */
    public function effectsList(): array
    {
        $effects = $this->effects;

        return is_array($effects) ? array_values($effects) : [];
    }

    /**
     * A short human-readable label for the research's area (or its type when
     * it has no area, e.g. plants).
     */
    public function areaLabel(): string
    {
        if ($this->area && isset(self::AREA_LABELS[$this->area])) {
            return self::AREA_LABELS[$this->area];
        }

        return $this->isPlant() ? 'Plantas' : '—';
    }
}
