<?php

namespace App\Services;

use App\Models\Aircraft;
use App\Models\Commander;
use App\Models\Fleet;
use App\Models\RankingBonus;
use App\Models\ShipDesign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\ResearchBonusService;

/**
 * Composes fleets and computes their aggregated combat stats.
 *
 * Rules:
 *  - up to 12 ship-design slots per fleet, each stacking <= 5000 ships;
 *  - ships come from the player's built inventory (aircraft.quantity) minus
 *    what's already reserved by other fleets; a design can't be over-assigned;
 *  - aggregate attack/hull/shield are the SUM across all ships; movement is
 *    the MINIMUM movement among the fleet's ships (the slowest sets the pace);
 *  - the leading commander's proficiency bonuses apply: the ship-class bonus
 *    scales that class's ships (attack + defense), the weapon bonus scales the
 *    attack coming from that weapon type. Commander-rank bonus applies overall.
 *  - the leading commander's attributes (pontaria/desvio/critico/velocidade)
 *    also shape combat: pontaria and critico raise the fleet's damage dealt,
 *    desvio raises its effective defense (damage taken drops), and velocidade
 *    adds to the fleet's movement. The exact tuning is provisional (see the
 *    ATTRIBUTE_*_PER_POINT constants) and will be revisited when combat is
 *    fleshed out.
 */
class FleetCompositionService
{
    /**
     * Provisional per-point contribution of each commander attribute, expressed
     * as a percentage of the relevant stat per attribute point. Kept small and
     * centralised so combat balancing is a one-line change later.
     */
    public const ATTRIBUTE_ATTACK_PCT_PER_PONTARIA = 0.5;   // +0.5% attack per pontaria point
    public const ATTRIBUTE_ATTACK_PCT_PER_CRITICO = 0.5;    // +0.5% attack per critico point
    public const ATTRIBUTE_DEFENSE_PCT_PER_DESVIO = 0.5;    // +0.5% defense per desvio point
    public const ATTRIBUTE_MOVEMENT_PER_VELOCIDADE = 0.1;   // +0.1 movement per velocidade point

    public function __construct(
        protected ShipDesignService $designs,
        protected ResearchBonusService $researchBonus,
    ) {
    }

    /**
     * How many ships of a design the player still has free to assign (owned
     * minus quantities reserved by fleets, optionally excluding one fleet).
     */
    public function availableForDesign(User $user, int $designId, ?int $excludeFleetId = null): int
    {
        $owned = (int) Aircraft::where('user_id', $user->id)
            ->where('ship_design_id', $designId)
            ->sum('quantity');

        $reservedQuery = DB::table('fleet_slots')
            ->join('fleets', 'fleets.id', '=', 'fleet_slots.fleet_id')
            ->where('fleets.user_id', $user->id)
            ->where('fleet_slots.ship_design_id', $designId);

        if ($excludeFleetId !== null) {
            $reservedQuery->where('fleets.id', '!=', $excludeFleetId);
        }

        $reserved = (int) $reservedQuery->sum('fleet_slots.quantity');

        return max(0, $owned - $reserved);
    }

    /**
     * Validate a proposed composition (list of {ship_design_id, quantity}).
     *
     * @param  array<int, array{ship_design_id:int, quantity:int}>  $slots
     *
     * @throws ValidationException
     */
    public function validate(User $user, array $slots, ?int $excludeFleetId = null): void
    {
        $slots = array_values(array_filter($slots, fn ($s) => (int) $s['quantity'] > 0));

        if (count($slots) < 1) {
            throw ValidationException::withMessages([
                'slots' => ['A frota precisa de pelo menos 1 nave.'],
            ]);
        }

        if (count($slots) > Fleet::MAX_SLOTS) {
            throw ValidationException::withMessages([
                'slots' => ['Máximo de '.Fleet::MAX_SLOTS.' modelos por frota.'],
            ]);
        }

        // Unique designs per fleet.
        $ids = array_map(fn ($s) => (int) $s['ship_design_id'], $slots);
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'slots' => ['Cada modelo só pode ocupar um slot na frota.'],
            ]);
        }

        foreach ($slots as $s) {
            $qty = (int) $s['quantity'];
            $designId = (int) $s['ship_design_id'];

            if ($qty > Fleet::MAX_PER_SLOT) {
                throw ValidationException::withMessages([
                    'slots' => ['Máximo de '.Fleet::MAX_PER_SLOT.' naves por slot.'],
                ]);
            }

            $design = ShipDesign::where('user_id', $user->id)->find($designId);
            if (! $design) {
                throw ValidationException::withMessages([
                    'slots' => ["Modelo inválido: {$designId}."],
                ]);
            }

            $available = $this->availableForDesign($user, $designId, $excludeFleetId);
            if ($qty > $available) {
                throw ValidationException::withMessages([
                    'slots' => ["Naves insuficientes de {$design->name}. Disponível: {$available}."],
                ]);
            }
        }
    }

    /**
     * Compute aggregated stats for a set of slots led by an optional commander.
     * When $user is provided, the player's researched weapon-damage and
     * aircraft-class attack bonuses are applied on top of commander bonuses.
     *
     * @param  array<int, array{ship_design_id:int, quantity:int}>  $slots
     */
    public function summarize(array $slots, ?Commander $commander = null, ?User $user = null): array
    {
        $bonuses = $this->bonusTable();

        $totalAttack = 0;
        $totalHull = 0;
        $totalShield = 0;
        $totalShips = 0;
        $minMovement = null;

        // Energy: the fleet's tank capacity is the sum of each ship's capacity
        // (base + modules) times its quantity. The per-round combat drain is
        // the sum of each surviving ship's upkeep, so we also expose the
        // per-ship upkeep totals to let the battle scale by surviving ships.
        $energyCapacity = 0;
        $energyUpkeep = 0;

        // Fleet-wide attribute bonuses from the leading commander. Computed once
        // from the commander's effective attributes at its current level.
        $attrAtkPct = 0.0;
        $attrDefPct = 0.0;
        $movementBonus = 0.0;
        if ($commander) {
            $attrs = $commander->attributes();
            $attrAtkPct = $attrs['pontaria'] * self::ATTRIBUTE_ATTACK_PCT_PER_PONTARIA
                + $attrs['critico'] * self::ATTRIBUTE_ATTACK_PCT_PER_CRITICO;
            $attrDefPct = $attrs['desvio'] * self::ATTRIBUTE_DEFENSE_PCT_PER_DESVIO;
            $movementBonus = $attrs['velocidade'] * self::ATTRIBUTE_MOVEMENT_PER_VELOCIDADE;
        }

        foreach ($slots as $s) {
            $qty = (int) $s['quantity'];
            if ($qty <= 0) {
                continue;
            }

            $design = ShipDesign::with(['baseType', 'modules.moduleType'])->find((int) $s['ship_design_id']);
            if (! $design) {
                continue;
            }

            $summary = $this->designs->summarize($design->baseType, $design->modules->map(fn ($dm) => [
                'module' => $dm->moduleType,
                'quantity' => (int) $dm->quantity,
            ])->all());

            $class = $design->baseType->class;
            $weaponType = $summary['weapon_type']; // null | machinegun | laser | missile

            // Per-ship base stats.
            $attack = (int) $summary['attack'];
            $hull = (int) $summary['hull'];
            $shield = (int) $summary['shield'];
            $movement = (int) $summary['movement'];
            $energyCapacity += (int) $summary['energy_capacity'] * $qty;
            $energyUpkeep += (int) $summary['energy_upkeep'] * $qty;

            // Commander proficiency bonuses (percent), if a commander leads.
            $atkPct = 0;
            $defPct = 0;
            if ($commander) {
                // Ship-class proficiency: attack + defense on that class.
                $classLevel = $commander->classProficiency($class);
                $atkPct += $bonuses['proficiency'][$classLevel]['attack'] ?? 0;
                $defPct += $bonuses['proficiency'][$classLevel]['defense'] ?? 0;

                // Weapon proficiency: attack only, on the ship's weapon type.
                if ($weaponType) {
                    $weaponLevel = $commander->weaponProficiency($weaponType);
                    $atkPct += $bonuses['proficiency'][$weaponLevel]['attack'] ?? 0;
                }

                // Commander rank: overall attack + defense.
                $rankLevel = (int) $commander->rank;
                $atkPct += $bonuses['commander'][$rankLevel]['attack'] ?? 0;
                $defPct += $bonuses['commander'][$rankLevel]['defense'] ?? 0;

                // Commander attributes: pontaria/critico raise damage dealt,
                // desvio raises effective defense (damage taken drops).
                $atkPct += $attrAtkPct;
                $defPct += $attrDefPct;
            }

            // Researched attack bonuses: per aircraft class and per weapon type
            // (applied on top of any commander bonuses).
            if ($user) {
                $atkPct += $this->researchBonus->classAttackPercent($user, $class);
                $atkPct += $this->researchBonus->weaponDamagePercent($user, $weaponType);
            }

            $attack = (int) round($attack * (100 + $atkPct) / 100);
            $hull = (int) round($hull * (100 + $defPct) / 100);
            $shield = (int) round($shield * (100 + $defPct) / 100);

            $totalAttack += $attack * $qty;
            $totalHull += $hull * $qty;
            $totalShield += $shield * $qty;
            $totalShips += $qty;
            $minMovement = $minMovement === null ? $movement : min($minMovement, $movement);
        }

        // Velocidade adds flat movement to the fleet's pace (the slowest ship
        // still sets the base). Only when the fleet actually has ships.
        $movement = $minMovement ?? 0;
        if ($movement > 0 && $movementBonus > 0) {
            $movement = (int) round($movement + $movementBonus);
        }

        return [
            'attack' => $totalAttack,
            'hull' => $totalHull,
            'shield' => $totalShield,
            'movement' => $movement,
            'ships' => $totalShips,
            // Max energy tank for this composition and the total per-round
            // combat drain when every ship is alive (scales down with losses).
            'energy_capacity' => $energyCapacity,
            'energy_upkeep' => $energyUpkeep,
        ];
    }

    /**
     * Ranking-bonus lookup keyed by scope + level.
     *
     * @return array{proficiency: array<int, array{attack:int, defense:int}>, commander: array<int, array{attack:int, defense:int}>}
     */
    protected function bonusTable(): array
    {
        $table = ['proficiency' => [], 'commander' => []];

        foreach (RankingBonus::all() as $b) {
            $table[$b->scope][$b->level] = [
                'attack' => (int) $b->attack_percent,
                'defense' => (int) $b->defense_percent,
            ];
        }

        return $table;
    }
}
