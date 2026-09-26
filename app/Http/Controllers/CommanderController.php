<?php

namespace App\Http\Controllers;

use App\Models\Commander;
use App\Services\CommanderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommanderController extends Controller
{
    public function __construct(protected CommanderService $commanders)
    {
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
                'color' => $c->definition->color,
                'image_url' => $c->definition->image_url,
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
