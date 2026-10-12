<?php

use App\Http\Controllers\EngineeringQaNormativeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('engineering/{organization}/projects/{project}/qa-normative')
    ->name('engineering.qa-normative.')
    ->group(function (): void {
        Route::get('/', [EngineeringQaNormativeController::class, 'index'])->name('index');
        Route::post('/scan', [EngineeringQaNormativeController::class, 'scan'])->name('scan');
        Route::post('/inspections/{inspection}/suggest', [EngineeringQaNormativeController::class, 'suggest'])->name('suggest');
        Route::post('/inspections/{inspection}/manual', [EngineeringQaNormativeController::class, 'manual'])->name('manual');
        Route::post('/links/{link}/decision', [EngineeringQaNormativeController::class, 'decide'])->name('decision');
    });
