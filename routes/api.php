<?php

use App\Http\Controllers\AircraftController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\CommanderController;
use App\Http\Controllers\FleetController;
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

    // Structures
    Route::post('/structures', [StructureController::class, 'store']);
    Route::post('/structures/collect', [StructureController::class, 'collectAll']);
    Route::post('/structures/{structure}/collect', [StructureController::class, 'collect']);
    Route::post('/structures/{structure}/upgrade', [StructureController::class, 'upgrade']);
    Route::patch('/structures/{structure}/move', [StructureController::class, 'move']);
    Route::delete('/structures/{structure}', [StructureController::class, 'demolish']);

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

    // Fleets (led by a commander, composed of ship designs)
    Route::get('/fleets', [FleetController::class, 'index']);
    Route::post('/fleets', [FleetController::class, 'store']);
    Route::put('/fleets/{fleet}', [FleetController::class, 'update']);
    Route::patch('/fleets/{fleet}/position', [FleetController::class, 'move']);
    Route::delete('/fleets/{fleet}', [FleetController::class, 'destroy']);
});
