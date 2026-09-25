<?php

namespace App\Http\Controllers;

use App\Models\AircraftType;
use App\Models\ModuleType;
use App\Models\ShipDesign;
use App\Models\ShipDesignModule;
use App\Services\ShipDesignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipDesignController extends Controller
{
    public function __construct(protected ShipDesignService $designs)
    {
    }

    /**
     * Catalog for the ship builder: base aircraft types + module types + the
     * player's saved designs.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $baseTypes = AircraftType::orderBy('class')->orderBy('id')->get()->map(fn (AircraftType $t) => [
            'id' => $t->id,
            'key' => $t->key,
            'class' => $t->class,
            'name' => $t->name,
            'color' => $t->color,
            'image_url' => $t->image_url,
            'storage' => $t->storage,
            'shield' => $t->shield,
            'hull' => $t->hull,
            'movement' => $t->movement,
            'build_time' => $t->build_time,
            'cost' => $t->cost(),
        ])->values();

        $modules = ModuleType::orderBy('id')->get()->map(fn (ModuleType $m) => [
            'id' => $m->id,
            'key' => $m->key,
            'name' => $m->name,
            'description' => $m->description,
            'color' => $m->color,
            'image_url' => $m->image_url,
            'movement' => $m->movement,
            'attack' => $m->attack,
            'hull' => $m->hull,
            'shield' => $m->shield,
            'space' => $m->space,
            'attack_type' => $m->attack_type,
            'range' => $m->range,
            'build_time_add' => $m->build_time_add,
            'cost' => $m->cost(),
            'is_weapon' => $m->isWeapon(),
            'special_attributes' => $m->special_attributes,
        ])->values();

        $designs = ShipDesign::where('user_id', $user->id)
            ->with(['baseType', 'modules.moduleType'])
            ->orderBy('id')
            ->get()
            ->map(fn (ShipDesign $d) => $this->designs->serialize($d))
            ->values();

        return response()->json([
            'base_types' => $baseTypes,
            'module_types' => $modules,
            'designs' => $designs,
        ]);
    }

    /**
     * Create a new ship design. Validates: min 1 module, space fits, at most
     * one weapon type.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'aircraft_type_id' => ['required', 'integer', 'exists:aircraft_types,id'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.module_type_id' => ['required', 'integer', 'exists:module_types,id'],
            'modules.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $base = AircraftType::findOrFail($data['aircraft_type_id']);
        $modules = $this->designs->resolveModules($data['modules']);

        // Throws ValidationException on invalid designs.
        $this->designs->validate($base, $modules);

        $design = DB::transaction(function () use ($user, $base, $data, $modules) {
            $design = ShipDesign::create([
                'user_id' => $user->id,
                'aircraft_type_id' => $base->id,
                'name' => $data['name'],
            ]);

            foreach ($modules as $m) {
                ShipDesignModule::create([
                    'ship_design_id' => $design->id,
                    'module_type_id' => $m['module']->id,
                    'quantity' => $m['quantity'],
                ]);
            }

            return $design;
        });

        return response()->json([
            'message' => 'Modelo de nave salvo.',
            'design' => $this->designs->serialize($design->fresh()),
        ], 201);
    }

    /**
     * Update an existing design (rename and/or re-fit modules).
     */
    public function update(Request $request, ShipDesign $shipDesign): JsonResponse
    {
        $this->authorizeDesign($request, $shipDesign);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'aircraft_type_id' => ['required', 'integer', 'exists:aircraft_types,id'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.module_type_id' => ['required', 'integer', 'exists:module_types,id'],
            'modules.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $base = AircraftType::findOrFail($data['aircraft_type_id']);
        $modules = $this->designs->resolveModules($data['modules']);
        $this->designs->validate($base, $modules);

        DB::transaction(function () use ($shipDesign, $base, $data, $modules) {
            $shipDesign->update([
                'aircraft_type_id' => $base->id,
                'name' => $data['name'],
            ]);

            $shipDesign->modules()->delete();
            foreach ($modules as $m) {
                ShipDesignModule::create([
                    'ship_design_id' => $shipDesign->id,
                    'module_type_id' => $m['module']->id,
                    'quantity' => $m['quantity'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Modelo atualizado.',
            'design' => $this->designs->serialize($shipDesign->fresh()),
        ]);
    }

    /**
     * Delete a design.
     */
    public function destroy(Request $request, ShipDesign $shipDesign): JsonResponse
    {
        $this->authorizeDesign($request, $shipDesign);
        $shipDesign->delete();

        return response()->json(['message' => 'Modelo removido.']);
    }

    /**
     * Preview aggregate stats/cost/build time for a would-be design without
     * saving it (used by the builder UI as the player fits modules).
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'aircraft_type_id' => ['required', 'integer', 'exists:aircraft_types,id'],
            'modules' => ['array'],
            'modules.*.module_type_id' => ['required', 'integer', 'exists:module_types,id'],
            'modules.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $base = AircraftType::findOrFail($data['aircraft_type_id']);
        $modules = $this->designs->resolveModules($data['modules'] ?? []);

        return response()->json([
            'summary' => $this->designs->summarize($base, $modules),
        ]);
    }

    protected function authorizeDesign(Request $request, ShipDesign $design): void
    {
        if ($design->user_id !== $request->user()->id) {
            abort(403, 'Esse modelo não pertence a você.');
        }
    }
}
