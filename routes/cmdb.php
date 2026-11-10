<?php

use App\Http\Controllers\Cmdb\ApplicationController;
use App\Http\Controllers\Cmdb\ApplicationFlowController;
use App\Http\Controllers\Cmdb\ApplicationImportController;
use App\Http\Controllers\Cmdb\DashboardController;
use App\Http\Controllers\Cmdb\DeploymentController;
use App\Http\Controllers\Cmdb\ImpactController;
use App\Http\Controllers\Cmdb\ReferenceController;
use App\Http\Controllers\Cmdb\ServerController;
use App\Http\Controllers\Cmdb\ServerImportController;
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
        // Import Excel / CSV (avant servers/{server} pour ne pas être capturé par la fiche)
        Route::get('servers/import', [ServerImportController::class, 'create'])->name('servers.import');
        Route::post('servers/import/preview', [ServerImportController::class, 'preview'])->name('servers.import.preview');
        Route::post('servers/import', [ServerImportController::class, 'store'])->name('servers.import.store');
        Route::delete('servers/import', [ServerImportController::class, 'destroy'])->name('servers.import.cancel');
        Route::get('servers/import/template', [ServerImportController::class, 'template'])->name('servers.import.template');
        Route::get('applications/import', [ApplicationImportController::class, 'create'])->name('applications.import');
        Route::post('applications/import/preview', [ApplicationImportController::class, 'preview'])->name('applications.import.preview');
        Route::post('applications/import', [ApplicationImportController::class, 'store'])->name('applications.import.store');
        Route::delete('applications/import', [ApplicationImportController::class, 'destroy'])->name('applications.import.cancel');
        Route::get('applications/import/template', [ApplicationImportController::class, 'template'])->name('applications.import.template');

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
