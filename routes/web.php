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

        Route::put('settings', [ConfigController::class, 'updateSettings'])->name('settings.update');
    });
});
