<?php

namespace App\Http\Controllers;

use App\Models\Commander;
use App\Models\Item;
use App\Services\CommanderService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommanderController extends Controller
{
    /** Item key that re-rolls a commander's growth factors. */
    public const REROLL_ITEM_KEY = 'pergaminho_do_caminho';

    public function __construct(
        protected CommanderService $commanders,
        protected InventoryService $inventory,
    ) {
    }

    /**
     * The player's commander pool + recruitment status + limits.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->commanders->settle($user);

        return response()->json($this->snapshot($request));
    }

    /**
     * Recruit a commander immediately, then start a one-hour cooldown before
     * the next one (requires a built hangar, pool cap 30).
     */
    public function recruit(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->commanders->recruit($user);

        return response()->json([
            'message' => 'Comandante recrutado! Você poderá recrutar outro em 1 hora.',
            ...$this->snapshot($request),
        ], 201);
    }

    /**
     * Consume a "Pergaminho do Caminho" to re-roll a commander's growth
     * factors. Requires owning the item and the commander. The item is only
     * removed once the re-roll succeeds (both run in one transaction).
     */
    public function rerollGrowthFactors(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commander_id' => ['required', 'integer', 'exists:commanders,id'],
        ]);

        $user = $request->user();

        $commander = Commander::where('user_id', $user->id)
            ->findOrFail($data['commander_id']);

        $item = Item::where('key', self::REROLL_ITEM_KEY)->first();
        if (! $item) {
            throw ValidationException::withMessages([
                'item' => ['O Pergaminho do Caminho não está disponível.'],
            ]);
        }

        DB::transaction(function () use ($user, $item, $commander) {
            // Fails if the player doesn't own the item (frees us from a
            // separate ownership check here).
            $this->inventory->removeItem($user, $item, 1);
            $this->commanders->rerollGrowthFactors($user, $commander);
        });

        return response()->json([
            'message' => 'Fatores de crescimento re-rolados com o Pergaminho do Caminho.',
            ...$this->snapshot($request),
        ]);
    }

    protected function snapshot(Request $request): array
    {
        $user = $request->user();

        $pool = Commander::where('user_id', $user->id)
            ->with('definition')
            ->orderBy('id')
            ->get()
            ->map(fn (Commander $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'rank' => $c->rank,
                'rank_label' => Commander::ROMAN[$c->rank] ?? (string) $c->rank,
                'level' => $c->level,
                'level_max' => Commander::LEVEL_MAX,
                'color' => $c->definition->color,
                'image_url' => $c->definition->image_url,
                // Effective attribute values at the current level.
                'attributes' => $c->attributes(),
                // Per-attribute growth factors rolled at recruitment (0..10,
                // sum <= 20). Re-rollable with the Pergaminho do Caminho.
                'growth_factors' => [
                    'pontaria' => $c->growth_pontaria,
                    'desvio' => $c->growth_desvio,
                    'critico' => $c->growth_critico,
                    'velocidade' => $c->growth_velocidade,
                ],
                'natural_growth_per_level' => $c->definition->natural_growth_per_level,
                'proficiencies' => [
                    'cruiser' => $c->prof_cruiser,
                    'battleship' => $c->prof_battleship,
                    'frigate' => $c->prof_frigate,
                    'fighter' => $c->prof_fighter,
                    'machinegun' => $c->prof_machinegun,
                    'laser' => $c->prof_laser,
                    'missile' => $c->prof_missile,
                ],
            ])
            ->values();

        return [
            'commanders' => $pool,
            'recruitment' => [
                'can_recruit' => $this->commanders->canRecruit($user),
                'active' => $this->commanders->activeRecruitment($user) !== null,
                'remaining_seconds' => $this->commanders->recruitmentRemaining($user),
                'pool_used' => $this->commanders->poolCount($user),
                'pool_max' => CommanderService::POOL_MAX,
            ],
        ];
    }
}
