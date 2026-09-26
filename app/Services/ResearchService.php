<?php

namespace App\Services;

use App\Models\Base;
use App\Models\Item;
use App\Models\PlayerItem;
use App\Models\PlayerResearch;
use App\Models\ResearchDefinition;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the account-level research rules. Research is unlocked by the "Centro de
 * Pesquisa" (a unique research structure on the terrestrial base), whose level
 * reduces research WAIT TIME (never the gold cost or the required items),
 * capped at 60%.
 *
 * Research runs in two independent lanes: at most ONE technology and up to
 * THREE plants may be in progress at the same time. Gold is spent from the
 * shared terrestrial wallet. Plant research requires a plant blueprint in the
 * inventory and may consume extra inventory items.
 */
class ResearchService
{
    /** Maximum technologies that can be in progress at once. */
    public const MAX_ACTIVE_TECHNOLOGY = 1;

    /** Maximum plants that can be in progress at once. */
    public const MAX_ACTIVE_PLANTS = 3;

    public function __construct(protected InventoryService $inventory)
    {
    }

    /**
     * The player's built Centro de Pesquisa, if any. Unique research structure
     * on the terrestrial base, only counts once fully constructed.
     */
    public function center(User $user): ?Structure
    {
        $base = $this->terrestrialBase($user);
        if (! $base) {
            return null;
        }

        return $base->structures()
            ->with('type')
            ->get()
            ->first(fn (Structure $s) => $s->type->isResearch() && $s->is_constructed);
    }

    protected function terrestrialBase(User $user): ?Base
    {
        return Base::where('user_id', $user->id)->where('kind', 'terrestrial')->first();
    }

    /** Percentage the research time is reduced by (0 if no center built). */
    public function timeReduction(User $user): int
    {
        return (int) ($this->center($user)?->researchTimeReduction() ?? 0);
    }

    /**
     * All research currently in progress for this player. Any whose timer has
     * elapsed is settled (finalized) first and excluded from the result, so
     * this only ever returns research that is still genuinely running.
     *
     * @return Collection<int, PlayerResearch>
     */
    public function activeResearch(User $user): Collection
    {
        return PlayerResearch::where('user_id', $user->id)
            ->whereNotNull('finish_at')
            ->with('definition')
            ->get()
            // Lazily finalize any that are due; drop them from "active".
            ->reject(fn (PlayerResearch $pr) => $pr->settle())
            ->values();
    }

    /**
     * The in-progress research of a given type ('technology' | 'plant').
     *
     * @return Collection<int, PlayerResearch>
     */
    public function activeOfType(User $user, string $type): Collection
    {
        return $this->activeResearch($user)
            ->filter(fn (PlayerResearch $pr) => $pr->definition?->type === $type)
            ->values();
    }

    /**
     * Maximum concurrent research for a given type.
     */
    public function maxActiveForType(string $type): int
    {
        return $type === ResearchDefinition::TYPE_PLANT
            ? self::MAX_ACTIVE_PLANTS
            : self::MAX_ACTIVE_TECHNOLOGY;
    }

    /**
     * The player's completed research levels, keyed by definition id.
     *
     * @return array<int, int>
     */
    public function levels(User $user): array
    {
        return PlayerResearch::where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn (PlayerResearch $pr) => [$pr->research_definition_id => (int) $pr->level])
            ->all();
    }

    /**
     * Current completed level of a definition for a player (0 if none).
     */
    public function levelOf(User $user, ResearchDefinition $def): int
    {
        return (int) (PlayerResearch::where('user_id', $user->id)
            ->where('research_definition_id', $def->id)
            ->value('level') ?? 0);
    }

    /**
     * Whether every dependency of $def is satisfied at the required min level.
     *
     * @return array{ok:bool, missing:array<int, string>}
     */
    public function dependenciesMet(User $user, ResearchDefinition $def): array
    {
        $levels = $this->levels($user);
        $missing = [];

        foreach ($def->dependencies as $dep) {
            $required = (int) $dep->pivot->min_level;
            $have = (int) ($levels[$dep->id] ?? 0);
            if ($have < $required) {
                $missing[] = "{$dep->name} (nível {$required})";
            }
        }

        return ['ok' => $missing === [], 'missing' => $missing];
    }

    /**
     * Start researching a definition (its next level). Enforces: a built
     * Centro de Pesquisa, the per-type concurrency limit (1 technology, 3
     * plants), this exact research not already running, not already at max
     * level, dependencies met, enough gold, and — for plant research — the
     * required blueprint plus any extra items in the inventory (consuming them).
     *
     * @throws ValidationException
     */
    public function start(User $user, ResearchDefinition $def): PlayerResearch
    {
        $def->loadMissing('dependencies', 'requiredItems');

        $center = $this->center($user);
        if ($center === null) {
            throw ValidationException::withMessages([
                'research' => ['Construa um Centro de Pesquisa na base terrestre para pesquisar.'],
            ]);
        }

        // Concurrency limits are per type: 1 technology + up to 3 plants at a
        // time (already-settled ones don't count).
        $active = $this->activeResearch($user);

        // This specific research can't already be in progress.
        if ($active->contains(fn (PlayerResearch $pr) => $pr->research_definition_id === $def->id)) {
            throw ValidationException::withMessages([
                'busy' => ['Esta pesquisa já está em andamento.'],
            ]);
        }

        $activeSameType = $active->filter(fn (PlayerResearch $pr) => $pr->definition?->type === $def->type)->count();
        $max = $this->maxActiveForType($def->type);

        if ($activeSameType >= $max) {
            $message = $def->isPlant()
                ? "Você já tem {$max} pesquisas de plantas em andamento. Aguarde alguma terminar."
                : 'Já existe uma tecnologia em pesquisa. Aguarde a conclusão.';

            throw ValidationException::withMessages([
                'busy' => [$message],
            ]);
        }

        $current = $this->levelOf($user, $def);
        if ($current >= $def->max_level) {
            throw ValidationException::withMessages([
                'level' => ['Esta pesquisa já está no nível máximo.'],
            ]);
        }

        $targetLevel = $current + 1;

        // Dependencies (technologies).
        $deps = $this->dependenciesMet($user, $def);
        if (! $deps['ok']) {
            throw ValidationException::withMessages([
                'dependencies' => ['Requisitos não atendidos: '.implode(', ', $deps['missing']).'.'],
            ]);
        }

        // Gold cost from the shared wallet.
        $base = $this->terrestrialBase($user);
        $wallet = $base->wallet();
        $goldCost = $def->goldCostForLevel($targetLevel);

        if ($goldCost > 0 && $wallet->gold < $goldCost) {
            throw ValidationException::withMessages([
                'cost' => ["Ouro insuficiente. Necessário: {$goldCost} de ouro."],
            ]);
        }

        // Resolve required inventory items (plant blueprint + extra items).
        // Returns [ [PlayerItem $stack, int $qtyToConsume], ... ].
        $consumption = $this->resolveItemRequirements($user, $def);

        $baseTime = $def->baseTimeForLevel($targetLevel);
        $reduction = $this->timeReduction($user);
        $finalTime = (int) round($baseTime * (100 - $reduction) / 100);
        $finalTime = max(1, $finalTime);

        return DB::transaction(function () use ($user, $def, $wallet, $goldCost, $consumption, $targetLevel, $finalTime) {
            if ($goldCost > 0) {
                $wallet->decrement('gold', $goldCost);
            }

            // Consume the required inventory items.
            foreach ($consumption as [$stack, $qty]) {
                $this->inventory->removeItem($user, $stack->item, $qty);
            }

            $now = now();

            return PlayerResearch::updateOrCreate(
                ['user_id' => $user->id, 'research_definition_id' => $def->id],
                [
                    'target_level' => $targetLevel,
                    'started_at' => $now,
                    'finish_at' => $now->copy()->addSeconds($finalTime),
                ]
            );
        });
    }

    /**
     * Validate the inventory item requirements for a research and return the
     * stacks (and quantities) to consume. Throws if anything is missing.
     *
     * Requirements come from two places:
     *  - a plant blueprint (required_item_key), consumed only when
     *    consumes_required_item is true.
     *  - extra items in research_definition_items (always consumed).
     *
     * @return array<int, array{0: PlayerItem, 1: int}>
     */
    protected function resolveItemRequirements(User $user, ResearchDefinition $def): array
    {
        // Aggregate needed quantities per item key.
        $needed = [];

        if ($def->required_item_key) {
            // The blueprint must be present; consumed only if flagged.
            $needed[$def->required_item_key] = ($needed[$def->required_item_key] ?? 0)
                + ($def->consumes_required_item ? 1 : 0);
            // Ensure the key is tracked even when not consumed (presence check).
            if (! array_key_exists($def->required_item_key, $needed)) {
                $needed[$def->required_item_key] = 0;
            }
        }

        foreach ($def->requiredItems as $req) {
            $needed[$req->item_key] = ($needed[$req->item_key] ?? 0) + (int) $req->quantity;
        }

        if ($needed === []) {
            return [];
        }

        $consumption = [];

        foreach ($needed as $itemKey => $qtyToConsume) {
            $item = Item::where('key', $itemKey)->first();
            if ($item === null) {
                throw ValidationException::withMessages([
                    'items' => ["Item requerido não existe no catálogo ({$itemKey})."],
                ]);
            }

            $stack = PlayerItem::where('user_id', $user->id)
                ->where('item_id', $item->id)
                ->with('item')
                ->first();

            $have = $stack?->quantity ?? 0;

            // Presence check: the item must be owned (min 1) even if not consumed.
            $minRequired = max(1, $qtyToConsume);
            if ($have < $minRequired) {
                throw ValidationException::withMessages([
                    'items' => ["Item requerido ausente ou insuficiente no inventário: {$item->name} (x{$minRequired})."],
                ]);
            }

            if ($qtyToConsume > 0) {
                $consumption[] = [$stack, $qtyToConsume];
            }
        }

        return $consumption;
    }

    /**
     * All research definitions with the player's per-definition state, split
     * by type. Used to build the research panel snapshot.
     *
     * @return Collection<int, ResearchDefinition>
     */
    public function definitions(): Collection
    {
        return ResearchDefinition::with(['dependencies', 'requiredItems'])
            ->orderBy('type')
            ->orderBy('id')
            ->get();
    }
}
