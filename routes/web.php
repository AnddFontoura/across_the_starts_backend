<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ConfigController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.login'));

// --- Admin config panel (session-based auth) ---
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('/', [ConfigController::class, 'dashboard'])->name('dashboard');

        Route::get('structure-types/{structureType}', [ConfigController::class, 'editStructureType'])
            ->name('structure-types.edit');
        Route::put('structure-types/{structureType}', [ConfigController::class, 'updateStructureType'])
            ->name('structure-types.update');

        Route::post('structure-types/{structureType}/overrides', [ConfigController::class, 'saveLevelOverride'])
            ->name('structure-types.overrides.save');
        Route::delete('structure-types/{structureType}/overrides/{override}', [ConfigController::class, 'deleteLevelOverride'])
            ->name('structure-types.overrides.delete');

        // Aircraft
        Route::get('aircraft-matrix', [ConfigController::class, 'editAircraftMatrix'])
            ->name('aircraft-matrix.edit');
        Route::put('aircraft-matrix', [ConfigController::class, 'updateAircraftMatrix'])
            ->name('aircraft-matrix.update');
        Route::get('aircraft-types/create', [ConfigController::class, 'createAircraftType'])
            ->name('aircraft-types.create');
        Route::post('aircraft-types', [ConfigController::class, 'storeAircraftType'])
            ->name('aircraft-types.store');
        Route::get('aircraft-types/{aircraftType}', [ConfigController::class, 'editAircraftType'])
            ->name('aircraft-types.edit');
        Route::put('aircraft-types/{aircraftType}', [ConfigController::class, 'updateAircraftType'])
            ->name('aircraft-types.update');
        Route::delete('aircraft-types/{aircraftType}', [ConfigController::class, 'destroyAircraftType'])
            ->name('aircraft-types.destroy');

        // Modules
        Route::get('module-types/create', [ConfigController::class, 'createModuleType'])
            ->name('module-types.create');
        Route::post('module-types', [ConfigController::class, 'storeModuleType'])
            ->name('module-types.store');
        Route::get('module-types/{moduleType}', [ConfigController::class, 'editModuleType'])
            ->name('module-types.edit');
        Route::put('module-types/{moduleType}', [ConfigController::class, 'updateModuleType'])
            ->name('module-types.update');
        Route::delete('module-types/{moduleType}', [ConfigController::class, 'destroyModuleType'])
            ->name('module-types.destroy');

        Route::put('settings', [ConfigController::class, 'updateSettings'])->name('settings.update');
    });
});
