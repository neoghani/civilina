<?php

use App\Http\Controllers\EngineeringNormativeExplorerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('engineering/{organization}/projects/{project}/qa-normative-v22',[EngineeringNormativeExplorerController::class,'index'])->name('engineering.normative-v22.index');
    Route::post('engineering/{organization}/projects/{project}/qa-normative-v22/refresh',[EngineeringNormativeExplorerController::class,'refresh'])->name('engineering.normative-v22.refresh');
    Route::post('engineering/{organization}/projects/{project}/qa-normative-v22/scan',[EngineeringNormativeExplorerController::class,'deepScan'])->name('engineering.normative-v22.scan');
    Route::post('engineering/{organization}/projects/{project}/qa-normative-v22/run-agent',[EngineeringNormativeExplorerController::class,'runAgent'])->name('engineering.normative-v22.run-agent');
    Route::post('engineering/{organization}/projects/{project}/qa-normative-v22/inspections/{inspection}/suggest',[EngineeringNormativeExplorerController::class,'suggest'])->name('engineering.normative-v22.suggest');
});
