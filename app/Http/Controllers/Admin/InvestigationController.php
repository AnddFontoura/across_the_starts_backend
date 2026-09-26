<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AircraftType;
use App\Models\InvestigationDefinition;
use App\Models\InvestigationEnemyFleet;
use App\Models\InvestigationEnemySlot;
use App\Models\InvestigationPrize;
use App\Models\Item;
use App\Models\ModuleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin CRUD for "Investigação Interplanetária" definitions: the battle
 * template, its predefined enemy fleets (with a JSON ship loadout), and its
 * item prizes. Follows the session-based admin panel pattern (Blade views,
 * redirect-with-status).
 */
class InvestigationController extends Controller
{
    /** List all investigation definitions. */
    public function index(): View
    {
        return view('admin.investigations.index', [
            'investigations' => InvestigationDefinition::withCount(['enemyFleets', 'prizes'])
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.investigations.edit', [
            'investigation' => new InvestigationDefinition([
                'min_player_fleets' => 1,
                'max_player_fleets' => 1,
                'map_width' => 200,
                'map_height' => 200,
                'max_rounds' => 30,
                'exp_reward' => 100,
                'is_active' => true,
                'color' => '#7ec8e3',
            ]),
            'creating' => true,
            'aircraftTypes' => AircraftType::orderBy('id')->get(),
            'moduleTypes' => ModuleType::orderBy('id')->get(),
            'items' => Item::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateDefinition($request, null);

        $investigation = InvestigationDefinition::create($data);

        return redirect()
            ->route('admin.investigations.edit', $investigation)
            ->with('status', 'Investigação criada. Agora adicione frotas inimigas e prêmios.');
    }

    public function edit(InvestigationDefinition $investigation): View
    {
        $investigation->load(['enemyFleets.slots', 'prizes.item']);

        return view('admin.investigations.edit', [
            'investigation' => $investigation,
            'creating' => false,
            'aircraftTypes' => AircraftType::orderBy('id')->get(),
            'moduleTypes' => ModuleType::orderBy('id')->get(),
            'items' => Item::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, InvestigationDefinition $investigation): RedirectResponse
    {
        $data = $this->validateDefinition($request, $investigation);

        $investigation->update($data);

        return redirect()
            ->route('admin.investigations.edit', $investigation)
            ->with('status', 'Investigação atualizada.');
    }

    public function destroy(InvestigationDefinition $investigation): RedirectResponse
    {
        $investigation->delete();

        return redirect()
            ->route('admin.investigations.index')
            ->with('status', 'Investigação removida.');
    }

    // --- enemy fleets -------------------------------------------------------

    /**
     * Create or update an enemy fleet + its ship slots. The ship composition is
     * a JSON array of {aircraft_type_id, quantity, name?, modules:[{module_type_id,quantity}]}.
     */
    public function saveEnemyFleet(Request $request, InvestigationDefinition $investigation): RedirectResponse
    {
        $validated = $request->validate([
            'enemy_fleet_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'start_x' => ['required', 'integer', 'min:0'],
            'start_y' => ['required', 'integer', 'min:0'],
            'commander_velocidade' => ['required', 'integer', 'min:0'],
            'attack_percent' => ['required', 'integer', 'min:-100', 'max:1000'],
            'defense_percent' => ['required', 'integer', 'min:-100', 'max:100'],
            'ships' => ['required', 'string'],
        ]);

        $ships = json_decode($validated['ships'], true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($ships) || count($ships) < 1) {
            return back()->withInput()->withErrors([
                'ships' => 'Composição inválida. Use um array JSON de naves.',
            ]);
        }

        // Validate every stack references a real aircraft type + module ids.
        $typeIds = AircraftType::pluck('id')->all();
        $moduleIds = ModuleType::pluck('id')->all();
        foreach ($ships as $s) {
            if (! in_array((int) ($s['aircraft_type_id'] ?? 0), $typeIds, true)) {
                return back()->withInput()->withErrors(['ships' => 'aircraft_type_id inválido em uma das naves.']);
            }
            foreach (($s['modules'] ?? []) as $m) {
                if (! in_array((int) ($m['module_type_id'] ?? 0), $moduleIds, true)) {
                    return back()->withInput()->withErrors(['ships' => 'module_type_id inválido em uma das naves.']);
                }
            }
        }

        DB::transaction(function () use ($validated, $investigation, $ships) {
            $enemy = $validated['enemy_fleet_id']
                ? InvestigationEnemyFleet::where('investigation_definition_id', $investigation->id)
                    ->findOrFail($validated['enemy_fleet_id'])
                : new InvestigationEnemyFleet(['investigation_definition_id' => $investigation->id]);

            $enemy->fill([
                'name' => $validated['name'],
                'start_x' => $validated['start_x'],
                'start_y' => $validated['start_y'],
                'commander_velocidade' => $validated['commander_velocidade'],
                'attack_percent' => $validated['attack_percent'],
                'defense_percent' => $validated['defense_percent'],
            ]);
            $enemy->investigation_definition_id = $investigation->id;
            $enemy->save();

            $enemy->slots()->delete();
            foreach ($ships as $s) {
                InvestigationEnemySlot::create([
                    'investigation_enemy_fleet_id' => $enemy->id,
                    'aircraft_type_id' => (int) $s['aircraft_type_id'],
                    'name' => $s['name'] ?? null,
                    'modules' => array_values($s['modules'] ?? []),
                    'quantity' => max(1, (int) ($s['quantity'] ?? 1)),
                ]);
            }
        });

        return redirect()
            ->route('admin.investigations.edit', $investigation)
            ->with('status', 'Frota inimiga salva.');
    }

    public function deleteEnemyFleet(InvestigationDefinition $investigation, InvestigationEnemyFleet $enemyFleet): RedirectResponse
    {
        abort_unless($enemyFleet->investigation_definition_id === $investigation->id, 404);
        $enemyFleet->delete();

        return redirect()
            ->route('admin.investigations.edit', $investigation)
            ->with('status', 'Frota inimiga removida.');
    }

    // --- prizes -------------------------------------------------------------

    public function savePrize(Request $request, InvestigationDefinition $investigation): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'chance' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        InvestigationPrize::create([
            'investigation_definition_id' => $investigation->id,
            'item_id' => $validated['item_id'],
            'quantity' => $validated['quantity'],
            'chance' => $validated['chance'],
        ]);

        return redirect()
            ->route('admin.investigations.edit', $investigation)
            ->with('status', 'Prêmio adicionado.');
    }

    public function deletePrize(InvestigationDefinition $investigation, InvestigationPrize $prize): RedirectResponse
    {
        abort_unless($prize->investigation_definition_id === $investigation->id, 404);
        $prize->delete();

        return redirect()
            ->route('admin.investigations.edit', $investigation)
            ->with('status', 'Prêmio removido.');
    }

    // --- helpers ------------------------------------------------------------

    protected function validateDefinition(Request $request, ?InvestigationDefinition $existing): array
    {
        $keyRule = ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'];
        $keyRule[] = $existing
            ? 'unique:investigation_definitions,key,'.$existing->id
            : 'unique:investigation_definitions,key';

        $data = $request->validate([
            'key' => $keyRule,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_player_fleets' => ['required', 'integer', 'min:1', 'max:255'],
            'max_player_fleets' => ['required', 'integer', 'min:1', 'max:255'],
            'map_width' => ['required', 'integer', 'min:20', 'max:2000'],
            'map_height' => ['required', 'integer', 'min:20', 'max:2000'],
            'max_rounds' => ['required', 'integer', 'min:1', 'max:200'],
            'exp_reward' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        if ($data['max_player_fleets'] < $data['min_player_fleets']) {
            $data['max_player_fleets'] = $data['min_player_fleets'];
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
