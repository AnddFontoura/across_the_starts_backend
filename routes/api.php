<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaseController;
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
    Route::delete('/structures/{structure}', [StructureController::class, 'demolish']);
});
