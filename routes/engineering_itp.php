<?php

use App\Http\Controllers\EngineeringItpController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('engineering/{organization}/projects/{project}/itp')
    ->name('engineering.itp.')
    ->group(function (): void {
        Route::get('/', [EngineeringItpController::class, 'index'])->name('index');
        Route::post('/', [EngineeringItpController::class, 'store'])->name('store');
        Route::post('/{itp}/rows', [EngineeringItpController::class, 'addRow'])->name('rows.store');
        Route::post('/{itp}/submit-review', [EngineeringItpController::class, 'submitReview'])->name('submit-review');
        Route::post('/{itp}/approve', [EngineeringItpController::class, 'approve'])->name('approve');
    });
