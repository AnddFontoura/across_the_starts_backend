<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameSetting;
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
            'settings' => GameSetting::orderBy('key')->get(),
        ]);
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
            'category' => ['required', 'in:producer,storage,command'],
            'resource' => ['required', 'in:gold,metal,energy'],
            'color' => ['required', 'string', 'max:20'],
            'width' => ['required', 'integer', 'min:1'],
            'height' => ['required', 'integer', 'min:1'],
            'max_level' => ['required', 'integer', 'min:1', 'max:100'],
            'production_base' => ['required', 'integer', 'min:0'],
            'production_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'capacity_base' => ['required', 'integer', 'min:0'],
            'capacity_growth' => ['required', 'numeric', 'min:1', 'max:10'],
            'protection_base' => ['required', 'integer', 'min:0'],
            'protection_growth' => ['required', 'numeric', 'min:1', 'max:10'],
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
}
