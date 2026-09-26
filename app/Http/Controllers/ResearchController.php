<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PlayerItem;
use App\Models\ResearchDefinition;
use App\Services\ResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchController extends Controller
{
    public function __construct(protected ResearchService $research)
    {
    }

    /**
     * Full research snapshot: whether the player has a Centro de Pesquisa, the
     * current time reduction, the active research (if any) and every research
     * definition with the player's state (level, affordability, requirements).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    /**
     * Start the next level of a research definition.
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'research_definition_id' => ['required', 'integer', 'exists:research_definitions,id'],
        ]);

        $def = ResearchDefinition::findOrFail($data['research_definition_id']);
        $this->research->start($request->user(), $def);

        return response()->json([
            'message' => "Pesquisa iniciada: {$def->name}.",
            ...$this->snapshot($request),
        ], 201);
    }

    /**
     * Build the JSON snapshot used by the research UI.
     */
    protected function snapshot(Request $request): array
    {
        $user = $request->user();

        $center = $this->research->center($user);
        $reduction = $this->research->timeReduction($user);
        $levels = $this->research->levels($user);

        // All in-progress research (elapsed ones already settled). Split by
        // type so the UI can show the two lanes (1 technology, 3 plants).
        $active = $this->research->activeResearch($user);
        $activeTechCount = $active->filter(fn ($pr) => $pr->definition?->type === ResearchDefinition::TYPE_TECHNOLOGY)->count();
        $activePlantCount = $active->filter(fn ($pr) => $pr->definition?->type === ResearchDefinition::TYPE_PLANT)->count();
        // Set of definition ids currently being researched (for per-card state).
        $activeIds = $active->pluck('research_definition_id')->all();
        $maxTech = ResearchService::MAX_ACTIVE_TECHNOLOGY;
        $maxPlants = ResearchService::MAX_ACTIVE_PLANTS;

        // The player's owned item quantities, keyed by item key (for showing
        // whether plant requirements are met in the UI).
        $ownedByKey = PlayerItem::where('user_id', $user->id)
            ->with('item')
            ->get()
            ->mapWithKeys(fn (PlayerItem $pi) => [$pi->item->key => (int) $pi->quantity])
            ->all();

        $wallet = $center ? $center->base->wallet() : null;
        $gold = $wallet?->gold ?? \App\Models\Base::where('user_id', $user->id)
            ->where('kind', 'terrestrial')->value('gold') ?? 0;

        $definitions = $this->research->definitions()->map(function (ResearchDefinition $def) use ($levels, $reduction, $ownedByKey, $gold, $activeIds, $activeTechCount, $activePlantCount, $maxTech, $maxPlants) {
            $current = (int) ($levels[$def->id] ?? 0);
            $isMax = $current >= $def->max_level;
            $targetLevel = $isMax ? $current : $current + 1;

            $goldCost = $def->goldCostForLevel($targetLevel);
            $baseTime = $def->baseTimeForLevel($targetLevel);
            $finalTime = max(1, (int) round($baseTime * (100 - $reduction) / 100));

            // Dependency state.
            $deps = $def->dependencies->map(function ($dep) use ($levels) {
                $have = (int) ($levels[$dep->id] ?? 0);
                $required = (int) $dep->pivot->min_level;

                return [
                    'id' => $dep->id,
                    'key' => $dep->key,
                    'name' => $dep->name,
                    'min_level' => $required,
                    'current_level' => $have,
                    'met' => $have >= $required,
                ];
            })->values();
            $depsMet = $deps->every(fn ($d) => $d['met']);

            // Item requirements (plant blueprint + extra consumed items).
            $requirements = [];

            if ($def->required_item_key) {
                $item = Item::where('key', $def->required_item_key)->first();
                $owned = (int) ($ownedByKey[$def->required_item_key] ?? 0);
                $requirements[] = [
                    'item_key' => $def->required_item_key,
                    'name' => $item?->name ?? $def->required_item_key,
                    'quantity' => $def->consumes_required_item ? 1 : 0,
                    'is_blueprint' => true,
                    'consumed' => (bool) $def->consumes_required_item,
                    'owned' => $owned,
                    'met' => $owned >= 1,
                ];
            }

            foreach ($def->requiredItems as $req) {
                $item = Item::where('key', $req->item_key)->first();
                $owned = (int) ($ownedByKey[$req->item_key] ?? 0);
                $requirements[] = [
                    'item_key' => $req->item_key,
                    'name' => $item?->name ?? $req->item_key,
                    'quantity' => (int) $req->quantity,
                    'is_blueprint' => false,
                    'consumed' => true,
                    'owned' => $owned,
                    'met' => $owned >= (int) $req->quantity,
                ];
            }

            $itemsMet = collect($requirements)->every(fn ($r) => $r['met']);
            $canAfford = $gold >= $goldCost;

            // This exact research already running?
            $inProgress = in_array($def->id, $activeIds, true);
            // Is the lane for this type full? (1 technology / 3 plants).
            $laneFull = $def->isPlant()
                ? $activePlantCount >= $maxPlants
                : $activeTechCount >= $maxTech;

            return [
                'id' => $def->id,
                'key' => $def->key,
                'name' => $def->name,
                'description' => $def->description,
                'type' => $def->type,
                'area' => $def->area,
                'area_label' => $def->areaLabel(),
                'color' => $def->color,
                'icon' => $def->icon,
                'image_url' => $def->image_url,
                'current_level' => $current,
                'max_level' => $def->max_level,
                'is_max_level' => $isMax,
                'target_level' => $targetLevel,
                'gold_cost' => $goldCost,
                'base_time' => $baseTime,
                'research_time' => $finalTime,
                'dependencies' => $deps,
                'dependencies_met' => $depsMet,
                'requirements' => $requirements,
                'requirements_met' => $itemsMet,
                'can_afford' => $canAfford,
                // Whether this research is itself currently in progress.
                'in_progress' => $inProgress,
                // Whether the concurrency lane for this type is full.
                'lane_full' => $laneFull,
                // Whether "Pesquisar" should be enabled right now.
                'can_start' => ! $isMax && ! $inProgress && ! $laneFull
                    && $depsMet && $itemsMet && $canAfford,
            ];
        })->values();

        // Every in-progress research (technology + plants), for the two lanes.
        $activeList = $active->map(fn ($pr) => [
            'research_definition_id' => $pr->research_definition_id,
            'name' => $pr->definition?->name,
            'type' => $pr->definition?->type,
            'target_level' => $pr->target_level,
            'remaining_seconds' => $pr->remainingSeconds(),
            'finish_at' => optional($pr->finish_at)->toIso8601String(),
        ])->values();

        return [
            'research' => [
                'has_center' => $center !== null,
                'center_level' => $center?->level ?? 0,
                'time_reduction' => $reduction,
                'gold' => $gold,
                // All in-progress research.
                'active' => $activeList,
                // Concurrency: 1 technology + 3 plants.
                'technology' => [
                    'used' => $activeTechCount,
                    'max' => $maxTech,
                ],
                'plants' => [
                    'used' => $activePlantCount,
                    'max' => $maxPlants,
                ],
            ],
            'definitions' => $definitions,
        ];
    }
}
