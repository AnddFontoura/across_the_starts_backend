<?php

namespace App\Services;

use App\Models\StructureType;

/**
 * Computes level-dependent values (production/hour, capacity, protection and
 * multi-resource upgrade cost) for a structure type.
 *
 * Default behavior is a geometric formula:
 *     value(level) = round(base * growth^(level - 1))
 *
 * Any level may be overridden via structure_level_configs. When an override
 * row provides a non-null value for a field, that value wins over the formula.
 */
class StructureLevelCalculator
{
    /**
     * In-memory cache of overrides keyed by "typeId:level".
     *
     * @var array<string, \App\Models\StructureLevelConfig|null>
     */
    protected array $overrideCache = [];

    /**
     * Clamp a level into the valid [1, max_level] range for a type.
     */
    public function clampLevel(StructureType $type, int $level): int
    {
        return max(1, min($level, $type->max_level));
    }

    /**
     * Production per hour at a given level (producers only).
     */
    public function productionPerHour(StructureType $type, int $level): int
    {
        $level = $this->clampLevel($type, $level);

        $override = $this->override($type, $level)?->production_per_hour;
        if ($override !== null) {
            return (int) $override;
        }

        return $this->geometric($type->production_base, (float) $type->production_growth, $level);
    }

    /**
     * Maximum storable amount at a given level (producers: production ceiling).
     */
    public function maxCapacity(StructureType $type, int $level): int
    {
        $level = $this->clampLevel($type, $level);

        $override = $this->override($type, $level)?->max_capacity;
        if ($override !== null) {
            return (int) $override;
        }

        return $this->geometric($type->capacity_base, (float) $type->capacity_growth, $level);
    }

    /**
     * Amount of EACH resource kept safe at a given level (storage only).
     */
    public function protection(StructureType $type, int $level): int
    {
        $level = $this->clampLevel($type, $level);

        $override = $this->override($type, $level)?->protection;
        if ($override !== null) {
            return (int) $override;
        }

        return $this->geometric($type->protection_base, (float) $type->protection_growth, $level);
    }

    /**
     * Cost (per resource) to upgrade FROM $level TO $level + 1.
     * Returns null when already at max level, otherwise an array with keys
     * gold, metal, energy.
     *
     * @return array{gold:int, metal:int, energy:int}|null
     */
    public function upgradeCost(StructureType $type, int $level): ?array
    {
        if ($level >= $type->max_level) {
            return null;
        }

        // Cost is indexed by the target level (level + 1).
        $targetLevel = $level + 1;
        $override = $this->override($type, $targetLevel);

        return [
            'gold' => $override?->upgrade_cost_gold
                ?? $this->geometric($type->upgrade_cost_gold_base, (float) $type->upgrade_cost_gold_growth, $targetLevel),
            'metal' => $override?->upgrade_cost_metal
                ?? $this->geometric($type->upgrade_cost_metal_base, (float) $type->upgrade_cost_metal_growth, $targetLevel),
            'energy' => $override?->upgrade_cost_energy
                ?? $this->geometric($type->upgrade_cost_energy_base, (float) $type->upgrade_cost_energy_growth, $targetLevel),
        ];
    }

    /**
     * Time (in seconds) to upgrade FROM $level TO $level + 1.
     * Returns null when already at max level.
     */
    public function upgradeTime(StructureType $type, int $level): ?int
    {
        if ($level >= $type->max_level) {
            return null;
        }

        $targetLevel = $level + 1;

        $override = $this->override($type, $targetLevel)?->upgrade_time;
        if ($override !== null) {
            return (int) $override;
        }

        return $this->geometric($type->upgrade_time_base, (float) $type->upgrade_time_growth, $targetLevel);
    }

    /**
     * value(level) = round(base * growth^(level - 1))
     */
    protected function geometric(int $base, float $growth, int $level): int
    {
        if ($base <= 0) {
            return 0;
        }

        return (int) round($base * ($growth ** ($level - 1)));
    }

    /**
     * Look up (and cache) the override row for a type/level, if any.
     */
    protected function override(StructureType $type, int $level): ?\App\Models\StructureLevelConfig
    {
        $cacheKey = $type->id.':'.$level;

        if (! array_key_exists($cacheKey, $this->overrideCache)) {
            $this->overrideCache[$cacheKey] = $type->relationLoaded('levelConfigs')
                ? $type->levelConfigs->firstWhere('level', $level)
                : $type->levelConfigs()->where('level', $level)->first();
        }

        return $this->overrideCache[$cacheKey];
    }
}
