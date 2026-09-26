<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AircraftMatchup;
use App\Models\AircraftType;
use App\Models\CommanderDefinition;
use App\Models\GameSetting;
use App\Models\ModuleType;
use App\Models\RankingBonus;
use App\Models\ResearchDefinition;
use App\Models\ResearchDefinitionItem;
use App\Models\StructureLevelConfig;
use App\Models\StructureType;
use App\Services\StructureLevelCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfigController extends Controller
{
    public function __construct(protected StructureLevelCalculator $calculator)
    {
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'structureTypes' => StructureType::orderBy('id')->get(),
            'aircraftTypes' => AircraftType::orderBy('id')->get(),
            'moduleTypes' => ModuleType::orderBy('id')->get(),
            'commanderDefinitions' => CommanderDefinition::orderBy('id')->get(),
            'researchDefinitions' => ResearchDefinition::orderBy('type')->orderBy('id')->get(),
            'settings' => GameSetting::orderBy('key')->get(),
        ]);
    }

    /**
     * Ranking-bonus editor: proficiency levels I-V and commander ranks I-X.
     */
    public function editRankingBonuses(): View
    {
        $bonuses = RankingBonus::all()->keyBy(fn (RankingBonus $b) => $b->scope.':'.$b->level);

        return view('admin.ranking_bonuses', [
            'bonuses' => $bonuses,
            'proficiencyLevels' => range(1, 5),
            'commanderLevels' => range(1, 10),
            'roman' => \App\Models\Commander::ROMAN,
        ]);
    }

    /**
     * Save the whole ranking-bonus table.
     */
    public function updateRankingBonuses(Request $request): RedirectResponse
    {
        $request->validate([
            'attack' => ['array'],
            'attack.*.*' => ['nullable', 'integer', 'min:-100', 'max:1000'],
            'defense' => ['array'],
            'defense.*.*' => ['nullable', 'integer', 'min:-100', 'max:1000'],
        ]);

        $attack = $request->input('attack', []);
        $defense = $request->input('defense', []);

        $scopes = [
            RankingBonus::SCOPE_PROFICIENCY => range(1, 5),
            RankingBonus::SCOPE_COMMANDER => range(1, 10),
        ];

        foreach ($scopes as $scope => $levels) {
            foreach ($levels as $level) {
                RankingBonus::updateOrCreate(
                    ['scope' => $scope, 'level' => $level],
                    [
                        'attack_percent' => (int) ($attack[$scope][$level] ?? 0),
                        'defense_percent' => (int) ($defense[$scope][$level] ?? 0),
                    ]
                );
            }
        }

        return redirect()
            ->route('admin.ranking-bonuses.edit')
            ->with('status', 'Bônus de ranking atualizados.');
    }

    /**
     * Show the form to create a new module type.
     */
    public function createModuleType(): View
    {
        return view('admin.module_type', [
            'module' => new ModuleType(['space' => 1, 'color' => '#8e7cc3']),
            'creating' => true,
        ]);
    }

    /**
     * Store a new module type.
     */
    public function storeModuleType(Request $request): RedirectResponse
    {
        $data = $this->validateModuleType($request, null);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $module = ModuleType::create($data);

        return redirect()
            ->route('admin.module-types.edit', $module)
            ->with('status', 'Módulo criado.');
    }

    /**
     * Delete a module type.
     */
    public function destroyModuleType(ModuleType $moduleType): RedirectResponse
    {
        $moduleType->delete();

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Módulo removido.');
    }

    /**
     * Edit a module type.
     */
    public function editModuleType(ModuleType $moduleType): View
    {
        return view('admin.module_type', [
            'module' => $moduleType,
            'creating' => false,
        ]);
    }

    /**
     * Update a module type. Any attribute may be 0. attack_type is only
     * meaningful when attack > 0; empty attack_type = not a weapon.
     */
    public function updateModuleType(Request $request, ModuleType $moduleType): RedirectResponse
    {
        $data = $this->validateModuleType($request, $moduleType);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $moduleType->update($data);

        return redirect()
            ->route('admin.module-types.edit', $moduleType)
            ->with('status', 'Módulo atualizado.');
    }

    /**
     * Validate + normalize a module type payload (create or update).
     *
     * @return array|RedirectResponse
     */
    protected function validateModuleType(Request $request, ?ModuleType $existing)
    {
        $keyRule = ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'];
        $keyRule[] = $existing
            ? 'unique:module_types,key,'.$existing->id
            : 'unique:module_types,key';

        $data = $request->validate([
            'key' => $keyRule,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['required', 'string', 'max:20'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'movement' => ['required', 'integer', 'min:0'],
            'attack' => ['required', 'integer', 'min:0'],
            'hull' => ['required', 'integer', 'min:0'],
            'shield' => ['required', 'integer', 'min:0'],
            'space' => ['required', 'integer', 'min:1'],
            'attack_type' => ['nullable', 'in:machinegun,laser,missile'],
            'range' => ['required', 'integer', 'min:0'],
            'build_time_add' => ['required', 'integer', 'min:0'],
            'cost_gold' => ['required', 'integer', 'min:0'],
            'cost_metal' => ['required', 'integer', 'min:0'],
            'cost_energy' => ['required', 'integer', 'min:0'],
            'special_attributes' => ['nullable', 'string'],
        ]);

        // Normalize: no attack => not a weapon.
        if ((int) $data['attack'] <= 0) {
            $data['attack_type'] = null;
        }
        $data['attack_type'] = $data['attack_type'] ?: null;

        $raw = trim((string) ($data['special_attributes'] ?? ''));
        if ($raw === '') {
            $data['special_attributes'] = null;
        } else {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withInput()->withErrors(['special_attributes' => 'JSON inválido em atributos especiais.']);
            }
            $data['special_attributes'] = $decoded;
        }

        return $data;
    }

    /**
     * Show the form to create a new aircraft type.
     */
    public function createAircraftType(): View
    {
        return view('admin.aircraft_type', [
            'type' => new AircraftType(['class' => 'cruiser', 'color' => '#8e7cc3']),
            'creating' => true,
        ]);
    }

    /**
     * Store a new aircraft type.
     */
    public function storeAircraftType(Request $request): RedirectResponse
    {
        $data = $this->validateAircraftType($request, null);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $type = AircraftType::create($data);

        return redirect()
            ->route('admin.aircraft-types.edit', $type)
            ->with('status', 'Aeronave criada.');
    }

    /**
     * Delete an aircraft type.
     */
    public function destroyAircraftType(AircraftType $aircraftType): RedirectResponse
    {
        $aircraftType->delete();

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Aeronave removida.');
    }

    /**
     * Edit an aircraft type's stats.
     */
    public function editAircraftType(AircraftType $aircraftType): View
    {
        return view('admin.aircraft_type', [
            'type' => $aircraftType,
            'creating' => false,
        ]);
    }

    /**
     * Update an aircraft type's stats. Aircraft are flat (no levels) and have
     * no innate damage — damage comes from modules. special_attributes is a
     * free-form JSON object.
     */
    public function updateAircraftType(Request $request, AircraftType $aircraftType): RedirectResponse
    {
        $data = $this->validateAircraftType($request, $aircraftType);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $aircraftType->update($data);

        return redirect()
            ->route('admin.aircraft-types.edit', $aircraftType)
            ->with('status', 'Aeronave atualizada.');
    }

    /**
     * Validate + normalize an aircraft type payload (create or update). Returns
     * the data array, or a RedirectResponse when the special JSON is invalid.
     *
     * @return array|RedirectResponse
     */
    protected function validateAircraftType(Request $request, ?AircraftType $existing)
    {
        $keyRule = ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'];
        $keyRule[] = $existing
            ? 'unique:aircraft_types,key,'.$existing->id
            : 'unique:aircraft_types,key';

        $data = $request->validate([
            'key' => $keyRule,
            'class' => ['required', 'in:'.implode(',', AircraftType::CLASSES)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['required', 'string', 'max:20'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'storage' => ['required', 'integer', 'min:0'],
            'cost_gold' => ['required', 'integer', 'min:0'],
            'cost_metal' => ['required', 'integer', 'min:0'],
            'cost_energy' => ['required', 'integer', 'min:0'],
            'build_time' => ['required', 'integer', 'min:0'],
            'shield' => ['required', 'integer', 'min:0'],
            'hull' => ['required', 'integer', 'min:0'],
            'movement' => ['required', 'integer', 'min:0'],
            'special_attributes' => ['nullable', 'string'],
        ]);

        $special = null;
        $raw = trim((string) ($data['special_attributes'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withInput()->withErrors(['special_attributes' => 'JSON inválido em atributos especiais.']);
            }
            $special = $decoded;
        }
        $data['special_attributes'] = $special;

        return $data;
    }

    /**
     * Show the counter matrix editor (bonus % and reduction % per ordered
     * attacker -> defender pair).
     */
    public function editAircraftMatrix(): View
    {
        // Matrix is between the four classes (not individual ships).
        $matchups = AircraftMatchup::all()
            ->keyBy(fn (AircraftMatchup $m) => $m->attacker_class.':'.$m->defender_class);

        return view('admin.aircraft_matrix', [
            'classes' => AircraftType::CLASSES,
            'labels' => AircraftType::CLASS_LABELS,
            'matchups' => $matchups,
        ]);
    }

    /**
     * Save the whole counter matrix in one submit.
     */
    public function updateAircraftMatrix(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bonus' => ['array'],
            'bonus.*.*' => ['nullable', 'integer', 'min:-100', 'max:1000'],
            'reduction' => ['array'],
            'reduction.*.*' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $bonus = $data['bonus'] ?? [];
        $reduction = $data['reduction'] ?? [];

        $classes = AircraftType::CLASSES;

        foreach ($classes as $attacker) {
            foreach ($classes as $defender) {
                $b = (int) ($bonus[$attacker][$defender] ?? 0);
                $r = (int) ($reduction[$attacker][$defender] ?? 0);

                AircraftMatchup::updateOrCreate(
                    ['attacker_class' => $attacker, 'defender_class' => $defender],
                    ['damage_bonus_percent' => $b, 'damage_reduction_percent' => $r]
                );
            }
        }

        return redirect()
            ->route('admin.aircraft-matrix.edit')
            ->with('status', 'Matriz de contra-tipos atualizada.');
    }

    /**
     * Edit a structure type's formula config plus per-level overrides.
     */
    public function editStructureType(StructureType $structureType): View
    {
        $structureType->load('levelConfigs');

        // Build a preview table of computed values for every level.
        $preview = [];
        for ($level = 1; $level <= $structureType->max_level; $level++) {
            $preview[] = [
                'level' => $level,
                'production' => $this->calculator->productionPerMinute($structureType, $level),
                'capacity' => $this->calculator->maxCapacity($structureType, $level),
                'protection' => $this->calculator->protection($structureType, $level),
                'hp' => $this->calculator->hp($structureType, $level),
                'damage' => $this->calculator->damage($structureType, $level),
                'range' => $this->calculator->range($structureType, $level),
                'build_time_reduction' => $this->calculator->buildTimeReduction($structureType, $level),
                'upgrade_cost' => $this->calculator->upgradeCost($structureType, $level),
                'upgrade_time' => $this->calculator->upgradeTime($structureType, $level),
                'override' => $structureType->levelConfigs->firstWhere('level', $level),
            ];
        }

        return view('admin.structure_type', [
            'type' => $structureType,
            'preview' => $preview,
        ]);
    }

    /**
     * Update the formula config for a structure type.
     */
    public function updateStructureType(Request $request, StructureType $structureType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'in:producer,storage,command,defense,support,inventory,research'],
            'scope' => ['required', 'in:terrestrial,planetary'],
            'resource' => ['required', 'in:gold,metal,energy'],
            'color' => ['required', 'string', 'max:20'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'width' => ['required', 'integer', 'min:1'],
            'height' => ['required', 'integer', 'min:1'],
            'max_level' => ['required', 'integer', 'min:1', 'max:100'],
            'production_base' => ['required', 'integer', 'min:0'],
            'production_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'capacity_base' => ['required', 'integer', 'min:0'],
            'capacity_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'protection_base' => ['required', 'integer', 'min:0'],
            'protection_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            // Combat stats (planetary structures). growth uses the same
            // convention as the others: 0 = linear (+base/level), >=1 geometric.
            'hp_base' => ['required', 'integer', 'min:0'],
            'hp_growth' => ['required', 'numeric', 'min:0', 'max:10'],
            'damage_base' => ['required', 'integer', 'min:0'],
            'damage_growth' => ['required', 'numeric', 'min:0', 'max:10'],
            // Attack range in cells (1 cell = 10x10 units).
            'range_base' => ['required', 'integer', 'min:0'],
            'range_growth' => ['required', 'numeric', 'min:0', 'max:10'],
            // Aircraft build-time reduction (%) — support structures.
            'build_time_reduction_base' => ['required', 'integer', 'min:0', 'max:100'],
            'build_time_reduction_growth' => ['required', 'numeric', 'min:0', 'max:10'],
            // Fleet governance (support structures / Aircraft Hangar).
            'build_slots_base' => ['required', 'integer', 'min:0'],
            'build_slots_growth' => ['required', 'numeric', 'min:0', 'max:10'],
            'build_slots_tiers' => ['nullable', 'string'],
            'fleet_capacity_base' => ['required', 'integer', 'min:0'],
            'fleet_capacity_growth' => ['required', 'numeric', 'min:0', 'max:10'],
            'upgrade_cost_gold_base' => ['required', 'integer', 'min:0'],
            'upgrade_cost_gold_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'upgrade_cost_metal_base' => ['required', 'integer', 'min:0'],
            'upgrade_cost_metal_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'upgrade_cost_energy_base' => ['required', 'integer', 'min:0'],
            'upgrade_cost_energy_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'build_time' => ['required', 'integer', 'min:0'],
            'upgrade_time_base' => ['required', 'integer', 'min:0'],
            'upgrade_time_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'is_unique' => ['nullable', 'boolean'],
            'structure_slots_base' => ['required', 'integer', 'min:0'],
            'structure_slots_growth' => ['required', 'numeric', 'min:0', 'max:10'],
        ]);

        // Unchecked checkbox doesn't submit; normalize to false.
        $data['is_unique'] = $request->boolean('is_unique');

        // Parse the build-slots tier table (JSON). Empty clears it (falls back
        // to the formula). Validate the shape: a list of {upTo, slots}.
        $rawTiers = trim((string) ($data['build_slots_tiers'] ?? ''));
        if ($rawTiers === '') {
            $data['build_slots_tiers'] = null;
        } else {
            $decoded = json_decode($rawTiers, true);
            $valid = is_array($decoded) && array_is_list($decoded);
            if ($valid) {
                foreach ($decoded as $tier) {
                    if (! is_array($tier) || ! isset($tier['upTo'], $tier['slots'])
                        || ! is_numeric($tier['upTo']) || ! is_numeric($tier['slots'])) {
                        $valid = false;
                        break;
                    }
                }
            }
            if (! $valid) {
                return back()->withInput()->withErrors([
                    'build_slots_tiers' => 'JSON inválido. Use uma lista como [{"upTo":8,"slots":1}, ...].',
                ]);
            }
            // Normalize to ints.
            $data['build_slots_tiers'] = array_map(fn ($t) => [
                'upTo' => (int) $t['upTo'],
                'slots' => (int) $t['slots'],
            ], $decoded);
        }

        $structureType->update($data);
        // Keep the display reference in sync with level 1 production (per minute).
        $structureType->update([
            'production_per_hour' => $this->calculator->productionPerMinute($structureType->fresh(), 1),
        ]);

        return redirect()
            ->route('admin.structure-types.edit', $structureType)
            ->with('status', 'Configuração da estrutura atualizada.');
    }

    /**
     * Create or update a per-level override.
     */
    public function saveLevelOverride(Request $request, StructureType $structureType): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'min:1', 'max:'.$structureType->max_level],
            'production_per_hour' => ['nullable', 'integer', 'min:0'],
            'max_capacity' => ['nullable', 'integer', 'min:0'],
            'protection' => ['nullable', 'integer', 'min:0'],
            'upgrade_cost_gold' => ['nullable', 'integer', 'min:0'],
            'upgrade_cost_metal' => ['nullable', 'integer', 'min:0'],
            'upgrade_cost_energy' => ['nullable', 'integer', 'min:0'],
            'upgrade_time' => ['nullable', 'integer', 'min:0'],
            'hp' => ['nullable', 'integer', 'min:0'],
            'damage' => ['nullable', 'integer', 'min:0'],
            'range' => ['nullable', 'integer', 'min:0'],
        ]);

        StructureLevelConfig::updateOrCreate(
            ['structure_type_id' => $structureType->id, 'level' => $data['level']],
            [
                'production_per_hour' => $data['production_per_hour'] ?? null,
                'max_capacity' => $data['max_capacity'] ?? null,
                'protection' => $data['protection'] ?? null,
                'upgrade_cost_gold' => $data['upgrade_cost_gold'] ?? null,
                'upgrade_cost_metal' => $data['upgrade_cost_metal'] ?? null,
                'upgrade_cost_energy' => $data['upgrade_cost_energy'] ?? null,
                'upgrade_time' => $data['upgrade_time'] ?? null,
                'hp' => $data['hp'] ?? null,
                'damage' => $data['damage'] ?? null,
                'range' => $data['range'] ?? null,
            ]
        );

        return redirect()
            ->route('admin.structure-types.edit', $structureType)
            ->with('status', "Override do nível {$data['level']} salvo.");
    }

    /**
     * Remove a per-level override (reverting to the formula).
     */
    public function deleteLevelOverride(StructureType $structureType, StructureLevelConfig $override): RedirectResponse
    {
        if ($override->structure_type_id === $structureType->id) {
            $override->delete();
        }

        return redirect()
            ->route('admin.structure-types.edit', $structureType)
            ->with('status', 'Override removido.');
    }

    /**
     * Update the global game settings.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $values = $request->input('settings', []);

        foreach ($values as $id => $value) {
            $setting = GameSetting::find($id);
            if ($setting) {
                $setting->update(['value' => $value]);
            }
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Configurações globais atualizadas.');
    }

    // -----------------------------------------------------------------------
    // Research definitions (technologies + plants)
    // -----------------------------------------------------------------------

    /**
     * Show the form to create a new research definition.
     */
    public function createResearchDefinition(): View
    {
        return view('admin.research_definition', [
            'def' => new ResearchDefinition([
                'type' => ResearchDefinition::TYPE_TECHNOLOGY,
                'area' => ResearchDefinition::AREA_TERRESTRIAL,
                'max_level' => 10,
                'gold_cost_growth' => 1.5,
                'research_time_growth' => 1.4,
                'color' => '#7ec8e3',
            ]),
            'creating' => true,
            'allDefinitions' => ResearchDefinition::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a new research definition.
     */
    public function storeResearchDefinition(Request $request): RedirectResponse
    {
        $data = $this->validateResearchDefinition($request, null);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $def = ResearchDefinition::create($data['fields']);
        $this->syncResearchRelations($def, $data);

        return redirect()
            ->route('admin.research-definitions.edit', $def)
            ->with('status', 'Pesquisa criada.');
    }

    /**
     * Edit a research definition.
     */
    public function editResearchDefinition(ResearchDefinition $researchDefinition): View
    {
        $researchDefinition->load(['dependencies', 'requiredItems']);

        return view('admin.research_definition', [
            'def' => $researchDefinition,
            'creating' => false,
            // Other definitions available as dependencies (exclude self).
            'allDefinitions' => ResearchDefinition::where('id', '!=', $researchDefinition->id)
                ->orderBy('name')->get(),
        ]);
    }

    /**
     * Update a research definition.
     */
    public function updateResearchDefinition(Request $request, ResearchDefinition $researchDefinition): RedirectResponse
    {
        $data = $this->validateResearchDefinition($request, $researchDefinition);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $researchDefinition->update($data['fields']);
        $this->syncResearchRelations($researchDefinition, $data);

        return redirect()
            ->route('admin.research-definitions.edit', $researchDefinition)
            ->with('status', 'Pesquisa atualizada.');
    }

    /**
     * Delete a research definition.
     */
    public function destroyResearchDefinition(ResearchDefinition $researchDefinition): RedirectResponse
    {
        $researchDefinition->delete();

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Pesquisa removida.');
    }

    /**
     * Validate + normalize a research definition payload. Returns an array
     * with 'fields' (model attributes), 'dependencies' and 'items', or a
     * RedirectResponse when the effects JSON is invalid.
     *
     * @return array|RedirectResponse
     */
    protected function validateResearchDefinition(Request $request, ?ResearchDefinition $existing)
    {
        $keyRule = ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'];
        $keyRule[] = $existing
            ? 'unique:research_definitions,key,'.$existing->id
            : 'unique:research_definitions,key';

        $data = $request->validate([
            'key' => $keyRule,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:technology,plant'],
            'area' => ['nullable', 'in:'.implode(',', ResearchDefinition::AREAS)],
            'gold_cost' => ['required', 'integer', 'min:0'],
            'gold_cost_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'research_time' => ['required', 'integer', 'min:1'],
            'research_time_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'max_level' => ['required', 'integer', 'min:1', 'max:100'],
            'required_item_key' => ['nullable', 'string', 'max:255'],
            'consumes_required_item' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'effects' => ['nullable', 'string'],
            // Dependencies: arrays of definition id + min level (parallel).
            'dep_id' => ['nullable', 'array'],
            'dep_id.*' => ['nullable', 'integer', 'exists:research_definitions,id'],
            'dep_level' => ['nullable', 'array'],
            'dep_level.*' => ['nullable', 'integer', 'min:1'],
            // Consumed items: arrays of item key + quantity (parallel).
            'item_key' => ['nullable', 'array'],
            'item_key.*' => ['nullable', 'string', 'max:255'],
            'item_qty' => ['nullable', 'array'],
            'item_qty.*' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = $data['type'];
        $isPlant = $type === ResearchDefinition::TYPE_PLANT;

        // Plants are always single-level and carry no area.
        if ($isPlant) {
            $data['max_level'] = 1;
            $data['area'] = null;
        }

        // Parse the effects JSON (a list of effect objects).
        $rawEffects = trim((string) ($data['effects'] ?? ''));
        $effects = null;
        if ($rawEffects !== '') {
            $decoded = json_decode($rawEffects, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || ! array_is_list($decoded)) {
                return back()->withInput()->withErrors([
                    'effects' => 'JSON inválido. Use uma lista como [{"type":"resource_production","resource":"gold","percent_per_level":5}].',
                ]);
            }
            foreach ($decoded as $eff) {
                if (! is_array($eff) || ! isset($eff['type'])) {
                    return back()->withInput()->withErrors([
                        'effects' => 'Cada efeito precisa ao menos de um campo "type".',
                    ]);
                }
            }
            $effects = $decoded;
        }

        $fields = [
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'area' => $data['area'] ?? null,
            'effects' => $effects,
            'gold_cost' => (int) $data['gold_cost'],
            'gold_cost_growth' => (float) $data['gold_cost_growth'],
            'research_time' => (int) $data['research_time'],
            'research_time_growth' => (float) $data['research_time_growth'],
            'max_level' => (int) $data['max_level'],
            'required_item_key' => $isPlant ? ($data['required_item_key'] ?: null) : null,
            'consumes_required_item' => $isPlant ? $request->boolean('consumes_required_item') : false,
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'image_url' => $data['image_url'] ?: null,
        ];

        // Build dependency + item pairs (ignoring blank rows).
        $dependencies = [];
        foreach (($data['dep_id'] ?? []) as $i => $depId) {
            if (! $depId) {
                continue;
            }
            $dependencies[(int) $depId] = ['min_level' => (int) ($data['dep_level'][$i] ?? 1)];
        }

        $items = [];
        foreach (($data['item_key'] ?? []) as $i => $itemKey) {
            $itemKey = trim((string) $itemKey);
            if ($itemKey === '') {
                continue;
            }
            $items[] = ['item_key' => $itemKey, 'quantity' => (int) ($data['item_qty'][$i] ?? 1)];
        }

        return ['fields' => $fields, 'dependencies' => $dependencies, 'items' => $items];
    }

    /**
     * Persist a research definition's dependencies and consumed-item rows.
     */
    protected function syncResearchRelations(ResearchDefinition $def, array $data): void
    {
        // Dependencies (can't depend on self).
        unset($data['dependencies'][$def->id]);
        $def->dependencies()->sync($data['dependencies']);

        // Consumed items: replace the whole set.
        ResearchDefinitionItem::where('research_definition_id', $def->id)->delete();
        foreach ($data['items'] as $req) {
            ResearchDefinitionItem::create([
                'research_definition_id' => $def->id,
                'item_key' => $req['item_key'],
                'quantity' => $req['quantity'],
            ]);
        }
    }
}
