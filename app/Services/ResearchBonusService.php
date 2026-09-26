<?php

namespace App\Services;

use App\Models\PlayerResearch;
use App\Models\ResearchDefinition;
use App\Models\User;

/**
 * Aggregates a player's COMPLETED research into the concrete gameplay bonuses
 * they grant. Research definitions carry a JSON list of effects (see the
 * `effects` column / admin editor); each effect is applied "per completed
 * level", so the final magnitude is (effect value) x (completed level).
 *
 * This is the single place that turns research levels into numbers the rest of
 * the game reads. It's the injection layer the sub-systems call:
 *  - Structure::productionPerMinute()  -> resourceProductionPercent()
 *  - Base::totalProtection()           -> warehouseProtectionFlat()
 *  - FleetCompositionService::summarize() -> weaponDamagePercent()/classAttackPercent()
 *
 * Supported effect shapes (all values are "per level"):
 *   {"type":"resource_production","resource":"gold|metal|energy|any","percent_per_level":N}
 *   {"type":"warehouse_protection","flat_per_level":N}
 *   {"type":"weapon_damage","weapon":"machinegun|laser|missile|any","percent_per_level":N}
 *   {"type":"aircraft_class_attack","class":"cruiser|battleship|frigate|fighter|any","percent_per_level":N}
 */
class ResearchBonusService
{
    /**
     * Per-request cache of aggregated bonuses, keyed by user id, so repeated
     * lookups during one request (e.g. collecting every structure) are cheap.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $cache = [];

    /**
     * The full aggregated bonus bag for a user.
     *
     * @return array{
     *   resource_production: array<string, int>,
     *   warehouse_protection_flat: int,
     *   weapon_damage: array<string, int>,
     *   class_attack: array<string, int>,
     * }
     */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return $this->empty();
        }

        if (isset($this->cache[$user->id])) {
            return $this->cache[$user->id];
        }

        $bonus = $this->empty();

        // Completed research rows (level >= 1) with their definitions.
        $rows = PlayerResearch::where('user_id', $user->id)
            ->where('level', '>=', 1)
            ->with('definition')
            ->get();

        foreach ($rows as $row) {
            $def = $row->definition;
            if ($def === null) {
                continue;
            }

            $level = (int) $row->level;

            foreach ($def->effectsList() as $effect) {
                $this->applyEffect($bonus, $effect, $level);
            }
        }

        return $this->cache[$user->id] = $bonus;
    }

    /**
     * Percentage boost to production of a specific resource (0 if none).
     * A "resource: any" effect applies to every resource.
     */
    public function resourceProductionPercent(?User $user, string $resource): int
    {
        $bonus = $this->forUser($user);

        return (int) ($bonus['resource_production'][$resource] ?? 0)
            + (int) ($bonus['resource_production']['any'] ?? 0);
    }

    /** Absolute (flat) warehouse protection bonus, per resource (0 if none). */
    public function warehouseProtectionFlat(?User $user): int
    {
        return (int) $this->forUser($user)['warehouse_protection_flat'];
    }

    /**
     * Percentage damage bonus for a given weapon type (0 if none). A
     * "weapon: any" effect applies to every weapon type.
     */
    public function weaponDamagePercent(?User $user, ?string $weapon): int
    {
        $bonus = $this->forUser($user);
        $any = (int) ($bonus['weapon_damage']['any'] ?? 0);

        if ($weapon === null) {
            return $any;
        }

        return (int) ($bonus['weapon_damage'][$weapon] ?? 0) + $any;
    }

    /**
     * Percentage attack bonus for a given aircraft class (0 if none). A
     * "class: any" effect applies to every class.
     */
    public function classAttackPercent(?User $user, ?string $class): int
    {
        $bonus = $this->forUser($user);
        $any = (int) ($bonus['class_attack']['any'] ?? 0);

        if ($class === null) {
            return $any;
        }

        return (int) ($bonus['class_attack'][$class] ?? 0) + $any;
    }

    /**
     * Clear the per-request cache (useful in tests after mutating research).
     */
    public function flush(?User $user = null): void
    {
        if ($user === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[$user->id]);
    }

    /**
     * Merge a single effect (scaled by the completed level) into the bag.
     */
    protected function applyEffect(array &$bonus, array $effect, int $level): void
    {
        $type = $effect['type'] ?? null;

        switch ($type) {
            case 'resource_production':
                $resource = (string) ($effect['resource'] ?? 'any');
                $amount = (int) round(($effect['percent_per_level'] ?? 0) * $level);
                $bonus['resource_production'][$resource] =
                    (int) ($bonus['resource_production'][$resource] ?? 0) + $amount;
                break;

            case 'warehouse_protection':
                $amount = (int) round(($effect['flat_per_level'] ?? 0) * $level);
                $bonus['warehouse_protection_flat'] += $amount;
                break;

            case 'weapon_damage':
                $weapon = (string) ($effect['weapon'] ?? 'any');
                $amount = (int) round(($effect['percent_per_level'] ?? 0) * $level);
                $bonus['weapon_damage'][$weapon] =
                    (int) ($bonus['weapon_damage'][$weapon] ?? 0) + $amount;
                break;

            case 'aircraft_class_attack':
                $class = (string) ($effect['class'] ?? 'any');
                $amount = (int) round(($effect['percent_per_level'] ?? 0) * $level);
                $bonus['class_attack'][$class] =
                    (int) ($bonus['class_attack'][$class] ?? 0) + $amount;
                break;

            default:
                // Unknown effect types are ignored (forward-compatible).
                break;
        }
    }

    /**
     * An empty bonus bag.
     */
    protected function empty(): array
    {
        return [
            'resource_production' => [],
            'warehouse_protection_flat' => 0,
            'weapon_damage' => [],
            'class_attack' => [],
        ];
    }
}
