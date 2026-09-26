<?php

use App\Http\Controllers\AircraftController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\BattleController;
use App\Http\Controllers\CommanderController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\GalaxyController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\ShipDesignController;
use App\Http\Controllers\StructureController;
use Illuminate\Support\Facades\Route;

// Public auth endpoints
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated endpoints (Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Base / terrain
    Route::get('/base', [BaseController::class, 'show']);

    // Galaxy: quadrant overview, planets in a quadrant, and attack-target info
    Route::get('/galaxy', [GalaxyController::class, 'index']);
    Route::get('/galaxy/quadrant/{quadrant}', [GalaxyController::class, 'show']);
    Route::get('/galaxy/planet/{base}', [GalaxyController::class, 'target']);

    // Structures
    Route::post('/structures', [StructureController::class, 'store']);
    Route::post('/structures/collect', [StructureController::class, 'collectAll']);
    Route::post('/structures/{structure}/collect', [StructureController::class, 'collect']);
    Route::post('/structures/{structure}/upgrade', [StructureController::class, 'upgrade']);
    Route::patch('/structures/{structure}/move', [StructureController::class, 'move']);
    Route::delete('/structures/{structure}', [StructureController::class, 'demolish']);

    // Inventory (account-level; item box = Forte Protetor)
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory/items', [InventoryController::class, 'addItem']);
    Route::delete('/inventory/items', [InventoryController::class, 'removeItem']);

    // Research (account-level; unlocked by the Centro de Pesquisa)
    Route::get('/research', [ResearchController::class, 'index']);
    Route::post('/research/start', [ResearchController::class, 'start']);

    // Aircraft / fleet (account-level)
    Route::get('/aircraft', [AircraftController::class, 'index']);
    Route::post('/aircraft/build', [AircraftController::class, 'build']);

    // Ship designs (custom models built from modules)
    Route::get('/ship-designs', [ShipDesignController::class, 'index']);
    Route::post('/ship-designs', [ShipDesignController::class, 'store']);
    Route::post('/ship-designs/preview', [ShipDesignController::class, 'preview']);
    Route::put('/ship-designs/{shipDesign}', [ShipDesignController::class, 'update']);
    Route::delete('/ship-designs/{shipDesign}', [ShipDesignController::class, 'destroy']);

    // Commanders (account pool, hourly recruitment)
    Route::get('/commanders', [CommanderController::class, 'index']);
    Route::post('/commanders/recruit', [CommanderController::class, 'recruit']);
    // Re-roll a commander's growth factors (consumes a Pergaminho do Caminho).
    Route::post('/commanders/reroll-growth', [CommanderController::class, 'rerollGrowthFactors']);

    // Fleets (led by a commander, composed of ship designs)
    Route::get('/fleets', [FleetController::class, 'index']);
    Route::post('/fleets', [FleetController::class, 'store']);
    Route::put('/fleets/{fleet}', [FleetController::class, 'update']);
    Route::patch('/fleets/{fleet}/position', [FleetController::class, 'move']);
    Route::delete('/fleets/{fleet}', [FleetController::class, 'destroy']);

    // Investigações interplanetárias (batalhas em tempo real, passo a passo)
    Route::get('/investigations', [BattleController::class, 'index']);
    Route::post('/investigations/start', [BattleController::class, 'start']);
    Route::get('/battles/active', [BattleController::class, 'active']);
    Route::get('/battles/{battle}', [BattleController::class, 'show']);
    Route::post('/battles/{battle}/step', [BattleController::class, 'step']);
    Route::post('/battles/{battle}/claim', [BattleController::class, 'claim']);
    Route::post('/battles/{battle}/abandon', [BattleController::class, 'abandon']);
});
