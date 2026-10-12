<?php

use App\Http\Controllers\EngineeringQaControlController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('engineering/{organization}/projects/{project}/qa-control')
    ->name('engineering.qa-control.')
    ->group(function (): void {
        Route::get('/', [EngineeringQaControlController::class, 'index'])->name('index');
        Route::post('/generate', [EngineeringQaControlController::class, 'generate'])->name('generate');
        Route::post('/inspections/{inspection}', [EngineeringQaControlController::class, 'updateInspection'])->name('inspections.update');
        Route::post('/inspections/{inspection}/evidences', [EngineeringQaControlController::class, 'uploadEvidence'])->name('evidences.store');
        Route::get('/evidences/{evidence}/download', [EngineeringQaControlController::class, 'downloadEvidence'])->name('evidences.download');
        Route::post('/inspections/{inspection}/register-entries', [EngineeringQaControlController::class, 'addRegisterEntry'])->name('register-entries.store');
        Route::post('/handovers/{handover}', [EngineeringQaControlController::class, 'updateHandover'])->name('handovers.update');
    });
