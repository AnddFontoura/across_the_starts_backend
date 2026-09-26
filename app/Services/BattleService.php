<?php

namespace App\Services;

use App\Models\Aircraft;
use App\Models\AircraftMatchup;
use App\Models\BattleEvent;
use App\Models\BattleFleet;
use App\Models\BattleInstance;
use App\Models\BattleShip;
use App\Models\Commander;
use App\Models\Fleet;
use App\Models\Item;
use App\Models\InvestigationPrize;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves an investigation battle one round at a time (server-authoritative;
 * the client steps rounds as the player watches). All actions are logged as
 * battle_events so the frontend can replay/animate and a returning player sees
 * the exact same fight.
 *
 * Round rules (per the design):
 *  - fleets act in initiative order: highest commander velocidade first;
 *  - on its turn a fleet moves toward the nearest enemy in orthogonal 10-unit
 *    steps forming a straight line or an L (never diagonal), up to its movement
 *    budget; if it ends within weapon range of an enemy it attacks and the
 *    target defends;
 *  - one round = every alive fleet acted once; a run lasts at most max_rounds,
 *    after which it expires with no winner;
 *  - ships destroyed here are removed permanently from the player's inventory.
 *
 * The exact attack maths is intentionally isolated in resolveDamage() so it can
 * be swapped when combat is fully specified. The current formula: attacker's
 * total attack, modified by the aircraft-type matchup, applied to the target's
 * pooled shield-then-hull, converting accumulated damage into whole ship kills.
 */
class BattleService
{
    /** Map step size: one movement point advances the fleet 10 units. */
    public const STEP = Fleet::SIZE; // 10

    public function __construct(protected InventoryService $inventory)
    {
    }

    /**
     * Advance the battle by a single round and persist the outcome. No-op (just
     * returns) when the instance is already over. Deterministic via the stored
     * seed + round number.
     */
    public function advanceRound(BattleInstance $instance): BattleInstance
    {
        if ($instance->isOver()) {
            return $instance;
        }

        $round = (int) $instance->current_round + 1;
        $seq = (int) (BattleEvent::where('battle_instance_id', $instance->id)->max('sequence') ?? 0);

        // Seed RNG deterministically for this instance+round.
        mt_srand((int) ($instance->seed % PHP_INT_MAX) + $round);

        DB::transaction(function () use ($instance, $round, &$seq) {
            $fleets = $instance->fleets()->with('ships')->get();

            $this->log($instance, $round, ++$seq, BattleEvent::TYPE_ROUND_START, null, null, [
                'round' => $round,
            ]);

            // Initiative: highest velocidade first; ties broken by side (player
            // first) then id for determinism.
            $order = $fleets->sort(function (BattleFleet $a, BattleFleet $b) {
                return [$b->velocidade, $a->side === BattleInstance::SIDE_PLAYER ? 0 : 1, $a->id]
                    <=> [$a->velocidade, $b->side === BattleInstance::SIDE_PLAYER ? 0 : 1, $b->id];
            })->values();

            foreach ($order as $actor) {
                /** @var BattleFleet $actor */
                $actor->refresh()->load('ships');
                if (! $actor->alive) {
                    continue;
                }

                // A stranded fleet (no energy) can't move or attack this round.
                // It stays put and remains a valid target. Fleets without an
                // upkeep cost (e.g. enemies) are never stranded.
                if ($this->isStranded($actor)) {
                    $this->log($instance, $round, ++$seq, BattleEvent::TYPE_MOVE, $actor->id, null, [
                        'stranded' => true,
                        'from' => ['x' => $actor->x, 'y' => $actor->y],
                        'to' => ['x' => $actor->x, 'y' => $actor->y],
                    ]);
                    continue;
                }

                // Battle may have ended mid-round (one side wiped out).
                if ($this->sideWipedOut($instance)) {
                    break;
                }

                $target = $this->nearestEnemy($instance, $actor);
                if (! $target) {
                    continue;
                }

                // Move toward the target unless already in range.
                if (! $this->inRange($actor, $target)) {
                    $this->moveToward($instance, $round, $seq, $actor, $target);
                    $actor->refresh();
                    $target->refresh()->load('ships');
                }

                // Attack if now in range.
                if ($this->inRange($actor, $target)) {
                    $this->attack($instance, $round, $seq, $actor, $target);
                }
            }

            $instance->current_round = $round;

            $this->log($instance, $round, ++$seq, BattleEvent::TYPE_ROUND_END, null, null, [
                'round' => $round,
            ]);

            $this->settleOutcomeIfDecided($instance, $round, $seq);
            $instance->save();
        });

        mt_srand(); // restore non-deterministic RNG

        return $instance->fresh();
    }

    // --- movement -----------------------------------------------------------

    /**
     * Move the actor toward the target in orthogonal 10-unit steps, spending up
     * to `movement` steps. The path is the shortest Manhattan route: close the
     * larger axis first (straight line), then the other (forming an L). Never
     * diagonal. Stops early if it reaches weapon range.
     */
    protected function moveToward(BattleInstance $instance, int $round, int &$seq, BattleFleet $actor, BattleFleet $target): void
    {
        $fromX = $actor->x;
        $fromY = $actor->y;
        $steps = max(1, (int) $actor->movement);
        $range = $this->rangeUnits($actor);

        for ($i = 0; $i < $steps; $i++) {
            if ($this->manhattan($actor, $target) <= $range) {
                break;
            }

            $dx = $target->x - $actor->x;
            $dy = $target->y - $actor->y;

            // Move along the axis with the greater remaining distance first, so
            // the route is a straight line then an L. One STEP per iteration,
            // clamped so we don't overshoot the target on that axis.
            if (abs($dx) >= abs($dy) && $dx !== 0) {
                $move = $this->clampStep($dx);
                $actor->x += $move;
            } elseif ($dy !== 0) {
                $move = $this->clampStep($dy);
                $actor->y += $move;
            } else {
                break;
            }
        }

        $actor->x = max(0, min($actor->x, max(0, (int) $instance->map_width - Fleet::SIZE)));
        $actor->y = max(0, min($actor->y, max(0, (int) $instance->map_height - Fleet::SIZE)));
        $actor->save();

        if ($actor->x !== $fromX || $actor->y !== $fromY) {
            $this->log($instance, $round, ++$seq, BattleEvent::TYPE_MOVE, $actor->id, $target->id, [
                'from' => ['x' => $fromX, 'y' => $fromY],
                'to' => ['x' => $actor->x, 'y' => $actor->y],
            ]);
        }
    }

    /** Advance one STEP toward a signed delta without overshooting. */
    protected function clampStep(int $delta): int
    {
        if ($delta > 0) {
            return min(self::STEP, $delta);
        }

        return max(-self::STEP, $delta);
    }

    // --- combat -------------------------------------------------------------

    /**
     * Resolve the actor attacking the target: total the actor's attack, apply
     * it to the target's pooled defense, and convert damage into whole-ship
     * kills. Logs the attack and any destruction. Marks the target dead when
     * all its ships are gone.
     */
    protected function attack(BattleInstance $instance, int $round, int &$seq, BattleFleet $actor, BattleFleet $target): void
    {
        $totalAttack = (int) $actor->ships->sum(fn (BattleShip $s) => (int) $s->attack * (int) $s->quantity_remaining);
        if ($totalAttack <= 0) {
            return;
        }

        // Attacking costs the actor energy (scaled by its surviving ships).
        // A stranded actor never reaches here (gated in the round loop).
        $this->spendEnergy($instance, $round, $seq, $actor, 'attack');

        // Defending costs the target energy too (scaled by its surviving
        // ships). Running out here doesn't stop it defending this hit, but it
        // will be unable to act on its own turn.
        $this->spendEnergy($instance, $round, $seq, $target, 'defense');

        $killsByStack = [];
        $totalKills = 0;
        $remainingDamage = $totalAttack;

        // Spread damage across the target's stacks (weakest per-ship hp first),
        // applying the aircraft-type matchup per stack.
        $stacks = $target->ships
            ->filter(fn (BattleShip $s) => $s->quantity_remaining > 0)
            ->sortBy(fn (BattleShip $s) => $s->perShipHp())
            ->values();

        foreach ($stacks as $stack) {
            if ($remainingDamage <= 0) {
                break;
            }

            $dealt = $this->resolveDamage($actor, $target, $stack, $remainingDamage);
            $result = $this->applyDamageToStack($stack, $dealt);

            $remainingDamage -= $result['damage_used'];

            if ($result['kills'] > 0) {
                $killsByStack[] = [
                    'battle_ship_id' => $stack->id,
                    'name' => $stack->name,
                    'kills' => $result['kills'],
                    'remaining' => $stack->quantity_remaining,
                ];
                $totalKills += $result['kills'];
            }
        }

        $this->log($instance, $round, ++$seq, BattleEvent::TYPE_ATTACK, $actor->id, $target->id, [
            'attack' => $totalAttack,
            'kills' => $totalKills,
            'stacks' => $killsByStack,
        ]);

        if ($totalKills > 0) {
            $this->log($instance, $round, ++$seq, BattleEvent::TYPE_DESTROY, $actor->id, $target->id, [
                'kills' => $totalKills,
                'stacks' => $killsByStack,
            ]);
        }

        // Target wiped out?
        $alive = $target->ships()->where('quantity_remaining', '>', 0)->exists();
        if (! $alive) {
            $target->alive = false;
            $target->save();
        }
    }

    // --- energy -------------------------------------------------------------

    /**
     * Whether a fleet is stranded: it has an upkeep cost but no energy left to
     * pay it. Fleets with no upkeep (energy_upkeep = 0, e.g. enemy fleets) are
     * treated as having unlimited energy and are never stranded.
     */
    protected function isStranded(BattleFleet $fleet): bool
    {
        return (int) $fleet->energy_upkeep > 0 && (int) $fleet->energy <= 0;
    }

    /** Number of ships still alive across a fleet's stacks. */
    protected function survivingShips(BattleFleet $fleet): int
    {
        return (int) $fleet->ships->sum(fn (BattleShip $s) => max(0, (int) $s->quantity_remaining));
    }

    /**
     * Drain a fleet's energy for one combat action (attack or defense). The
     * cost is the fleet's per-ship upkeep times its surviving ships, so it
     * falls as ships are lost. Fleets without upkeep spend nothing. The energy
     * is clamped at 0 and the change is logged for the replay.
     */
    protected function spendEnergy(BattleInstance $instance, int $round, int &$seq, BattleFleet $fleet, string $action): void
    {
        $perShip = (int) $fleet->energy_upkeep;
        if ($perShip <= 0) {
            return; // unlimited energy (e.g. enemy fleets)
        }

        $cost = $perShip * $this->survivingShips($fleet);
        if ($cost <= 0) {
            return;
        }

        $before = (int) $fleet->energy;
        $after = max(0, $before - $cost);
        if ($after === $before) {
            return;
        }

        $fleet->energy = $after;
        $fleet->save();

        $this->log($instance, $round, ++$seq, BattleEvent::TYPE_ENERGY, $fleet->id, null, [
            'action' => $action,
            'spent' => $before - $after,
            'energy' => $after,
        ]);
    }

    /**
     * SWAPPABLE combat maths. Given a raw damage budget from the attacker,
     * return how much effective damage lands on this target stack, after the
     * attacker-type vs defender-type matchup (bonus + reduction) and the
     * fleet-level defense percentage. Replace this method when the full attack
     * formula is specified.
     */
    protected function resolveDamage(BattleFleet $actor, BattleFleet $target, BattleShip $targetStack, int $rawDamage): int
    {
        // Representative attacker type = the actor's largest surviving stack.
        $attackerStack = $actor->ships
            ->filter(fn (BattleShip $s) => $s->quantity_remaining > 0)
            ->sortByDesc('quantity_remaining')
            ->first();

        $bonus = 0;
        $reduction = 0;
        if ($attackerStack) {
            $matchup = AircraftMatchup::where('attacker_class', $attackerStack->ship_class)
                ->where('defender_class', $targetStack->ship_class)
                ->first();
            if ($matchup) {
                $bonus = (int) $matchup->damage_bonus_percent;
                $reduction = (int) $matchup->damage_reduction_percent;
            }
        }

        $defPct = (int) $target->defense_percent;

        $factor = (100 + $bonus) / 100;
        $factor *= max(0, 100 - $reduction) / 100;
        $factor *= max(0, 100 - $defPct) / 100;

        return max(0, (int) round($rawDamage * $factor));
    }

    /**
     * Apply effective damage to a stack's pooled hp, converting it into whole
     * ships destroyed. Returns kills and how much of the damage budget was
     * consumed (so leftover can spill to the next stack).
     *
     * @return array{kills:int, damage_used:int}
     */
    protected function applyDamageToStack(BattleShip $stack, int $damage): array
    {
        if ($damage <= 0 || $stack->quantity_remaining <= 0) {
            return ['kills' => 0, 'damage_used' => 0];
        }

        $perShipHp = $stack->perShipHp();
        $before = (int) $stack->quantity_remaining;

        // Cap damage at what's needed to wipe the stack, so surplus spills over.
        $capacity = (int) $stack->hp_remaining;
        $used = min($damage, $capacity);

        $stack->hp_remaining = $capacity - $used;

        // Whole ships remaining = ceil(hp / perShipHp); the difference is kills.
        $shipsLeft = (int) ceil($stack->hp_remaining / $perShipHp);
        $shipsLeft = max(0, min($before, $shipsLeft));
        $kills = $before - $shipsLeft;

        $stack->quantity_remaining = $shipsLeft;
        if ($shipsLeft <= 0) {
            $stack->hp_remaining = 0;
        }
        $stack->save();

        return ['kills' => $kills, 'damage_used' => $used];
    }

    // --- targeting ----------------------------------------------------------

    /** The nearest alive enemy fleet to the actor (Manhattan distance). */
    protected function nearestEnemy(BattleInstance $instance, BattleFleet $actor): ?BattleFleet
    {
        return $instance->fleets()
            ->where('side', '!=', $actor->side)
            ->where('alive', true)
            ->get()
            ->sortBy(fn (BattleFleet $e) => $this->manhattan($actor, $e))
            ->first();
    }

    /** Whether the target sits within the actor's best weapon range. */
    protected function inRange(BattleFleet $actor, BattleFleet $target): bool
    {
        return $this->manhattan($actor, $target) <= $this->rangeUnits($actor);
    }

    /**
     * The actor's effective attack reach in map units. Weapon range is defined
     * in the same grid step as movement (each point of range = one 10-unit
     * cell); a fleet with no weapon still needs to be adjacent (one cell).
     */
    protected function rangeUnits(BattleFleet $actor): int
    {
        $maxRange = (int) $actor->ships
            ->filter(fn (BattleShip $s) => $s->quantity_remaining > 0)
            ->max('weapon_range');

        return max(self::STEP, $maxRange * self::STEP);
    }

    /** Manhattan (orthogonal) distance between two fleet markers. */
    protected function manhattan(BattleFleet $a, BattleFleet $b): int
    {
        return abs($a->x - $b->x) + abs($a->y - $b->y);
    }

    // --- outcome + settlement ----------------------------------------------

    /** Whether either side has no alive fleets left. */
    protected function sideWipedOut(BattleInstance $instance): bool
    {
        $playerAlive = $instance->fleets()
            ->where('side', BattleInstance::SIDE_PLAYER)->where('alive', true)->exists();
        $enemyAlive = $instance->fleets()
            ->where('side', BattleInstance::SIDE_ENEMY)->where('alive', true)->exists();

        return ! $playerAlive || ! $enemyAlive;
    }

    /**
     * Decide and record the battle result once a side is wiped out or the round
     * cap is reached, then run settlement (permanent losses). Rewards (prizes +
     * exp) are granted separately on claim.
     */
    protected function settleOutcomeIfDecided(BattleInstance $instance, int $round, int &$seq): void
    {
        $playerAlive = $instance->fleets()
            ->where('side', BattleInstance::SIDE_PLAYER)->where('alive', true)->exists();
        $enemyAlive = $instance->fleets()
            ->where('side', BattleInstance::SIDE_ENEMY)->where('alive', true)->exists();

        $status = null;
        if (! $enemyAlive && $playerAlive) {
            $status = BattleInstance::STATUS_WON;
        } elseif (! $playerAlive && $enemyAlive) {
            $status = BattleInstance::STATUS_LOST;
        } elseif (! $playerAlive && ! $enemyAlive) {
            // Mutual destruction counts as a loss (no winner to claim prizes).
            $status = BattleInstance::STATUS_LOST;
        } elseif ($round >= (int) $instance->max_rounds) {
            $status = BattleInstance::STATUS_EXPIRED;
        }

        if ($status === null) {
            return;
        }

        $instance->status = $status;
        $instance->finished_at = now();

        $this->log($instance, $round, ++$seq, BattleEvent::TYPE_BATTLE_END, null, null, [
            'status' => $status,
        ]);

        $this->settleLosses($instance);
    }

    /**
     * Apply the battle's ship losses permanently to the player's Aircraft
     * inventory, and free the dispatched fleets (their battle_fleet rows remain
     * for the replay, but the instance is no longer in progress so they unlock).
     * Idempotent via the `settled` flag.
     */
    public function settleLosses(BattleInstance $instance): void
    {
        if ($instance->settled) {
            return;
        }

        DB::transaction(function () use ($instance) {
            $user = $instance->user;

            $playerFleets = $instance->fleets()
                ->where('side', BattleInstance::SIDE_PLAYER)
                ->with('ships')
                ->get();

            foreach ($playerFleets as $bf) {
                // A fleet that ran out of energy during the run can't make the
                // trip home: even if some ships survived the fight, they are
                // lost. Treat every surviving ship as an additional loss.
                $stranded = $this->isStranded($bf);

                foreach ($bf->ships as $stack) {
                    $lost = $stranded
                        ? (int) $stack->quantity
                        : (int) $stack->quantity - (int) $stack->quantity_remaining;

                    if ($lost <= 0 || ! $stack->ship_design_id) {
                        continue;
                    }

                    $row = Aircraft::where('user_id', $user->id)
                        ->where('ship_design_id', $stack->ship_design_id)
                        ->first();
                    if (! $row) {
                        continue;
                    }

                    $row->quantity = max(0, (int) $row->quantity - $lost);
                    if ($row->quantity <= 0) {
                        $row->delete();
                    } else {
                        $row->save();
                    }
                }

                if ($stranded) {
                    $this->log($instance, (int) $instance->current_round, $this->nextSeq($instance), BattleEvent::TYPE_STRANDED_LOST, $bf->id, null, [
                        'reason' => 'out_of_energy',
                    ]);
                }

                // A fleet that lost every ship — or is stranded (destroyed for
                // being unable to return) — is disbanded entirely. Survivors
                // keep whatever energy they had left at the end of the fight.
                $survivors = ! $stranded && $bf->ships()->where('quantity_remaining', '>', 0)->exists();
                if ($bf->fleet_id) {
                    if (! $survivors) {
                        Fleet::where('id', $bf->fleet_id)->delete();
                    } else {
                        Fleet::where('id', $bf->fleet_id)->update(['energy' => (int) $bf->energy]);
                    }
                }
            }

            $instance->settled = true;
            $instance->save();
        });
    }

    /**
     * Grant the win rewards: item prizes (respecting drop chance) and commander
     * experience to every participating commander. Idempotent via
     * `rewards_claimed`. Returns a summary of what was granted.
     *
     * @return array{items: array<int, array{name:string, quantity:int}>, exp: int, commanders: array<int, array{id:int, name:string, levels:int}>}
     */
    public function claimRewards(BattleInstance $instance): array
    {
        $result = ['items' => [], 'exp' => 0, 'commanders' => []];

        if ($instance->status !== BattleInstance::STATUS_WON || $instance->rewards_claimed) {
            return $result;
        }

        DB::transaction(function () use ($instance, &$result) {
            $user = $instance->user;
            $definition = $instance->definition;

            // Item prizes.
            $prizes = InvestigationPrize::where('investigation_definition_id', $definition->id)
                ->with('item')
                ->get();

            foreach ($prizes as $prize) {
                $roll = mt_rand(1, 100);
                if ($roll > (int) $prize->chance) {
                    continue;
                }
                if (! $prize->item) {
                    continue;
                }
                try {
                    $this->inventory->addItem($user, $prize->item, (int) $prize->quantity);
                    $result['items'][] = [
                        'name' => $prize->item->name,
                        'quantity' => (int) $prize->quantity,
                    ];
                } catch (\Illuminate\Validation\ValidationException $e) {
                    // Inventory full / no fort: skip that prize silently rather
                    // than fail the whole claim. Surfaced in the summary as absent.
                }
            }

            // Commander experience to every participating commander.
            $exp = (int) $definition->exp_reward;
            $result['exp'] = $exp;
            if ($exp > 0) {
                $commanderIds = $instance->fleets()
                    ->where('side', BattleInstance::SIDE_PLAYER)
                    ->whereNotNull('commander_id')
                    ->pluck('commander_id')
                    ->unique();

                foreach ($commanderIds as $cid) {
                    $commander = Commander::find($cid);
                    if (! $commander) {
                        continue;
                    }
                    $levels = $commander->grantExperience($exp);
                    $result['commanders'][] = [
                        'id' => $commander->id,
                        'name' => $commander->name,
                        'levels' => $levels,
                    ];
                }
            }

            $instance->rewards_claimed = true;
            $instance->save();
        });

        return $result;
    }

    // --- logging ------------------------------------------------------------

    /** The next monotonic sequence number for events on this instance. */
    protected function nextSeq(BattleInstance $instance): int
    {
        return ((int) (BattleEvent::where('battle_instance_id', $instance->id)->max('sequence') ?? 0)) + 1;
    }

    protected function log(BattleInstance $instance, int $round, int $seq, string $type, ?int $actorId, ?int $targetId, array $payload = []): void
    {
        BattleEvent::create([
            'battle_instance_id' => $instance->id,
            'round' => $round,
            'sequence' => $seq,
            'type' => $type,
            'actor_fleet_id' => $actorId,
            'target_fleet_id' => $targetId,
            'payload' => $payload,
        ]);
    }
}
