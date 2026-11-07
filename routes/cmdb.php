<?php

use App\Http\Controllers\Cmdb\ApplicationController;
use App\Http\Controllers\Cmdb\ApplicationFlowController;
use App\Http\Controllers\Cmdb\DashboardController;
use App\Http\Controllers\Cmdb\DeploymentController;
use App\Http\Controllers\Cmdb\ImpactController;
use App\Http\Controllers\Cmdb\ReferenceController;
use App\Http\Controllers\Cmdb\ServerController;
use App\Http\Controllers\Cmdb\TopologyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Lecture : tous les utilisateurs connectés
    Route::get('servers/export', [ServerController::class, 'export'])->name('servers.export');
    Route::get('applications/export', [ApplicationController::class, 'export'])->name('applications.export');
    Route::get('topology', TopologyController::class)->name('topology');
    Route::get('impact', ImpactController::class)->name('impact');
    Route::get('references/{type?}', [ReferenceController::class, 'index'])->name('references.index');

    // Écriture : contributeurs et administrateurs
    Route::middleware('can:edit-cmdb')->group(function () {
        Route::resource('servers', ServerController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('applications', ApplicationController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);

        Route::post('applications/{application}/deployments', [DeploymentController::class, 'store'])->name('deployments.store');
        Route::delete('deployments/{deployment}', [DeploymentController::class, 'destroy'])->name('deployments.destroy');
        Route::post('applications/{application}/flows', [ApplicationFlowController::class, 'store'])->name('flows.store');
        Route::delete('flows/{flow}', [ApplicationFlowController::class, 'destroy'])->name('flows.destroy');

        Route::post('references/{type}', [ReferenceController::class, 'store'])->name('references.store');
        Route::put('references/{type}/{id}', [ReferenceController::class, 'update'])->name('references.update');
        Route::delete('references/{type}/{id}', [ReferenceController::class, 'destroy'])->name('references.destroy');
    });

    Route::resource('servers', ServerController::class)->only(['index', 'show']);
    Route::resource('applications', ApplicationController::class)->only(['index', 'show']);
});
