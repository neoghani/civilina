<?php

use App\Http\Controllers\EngineeringNormativeRegistryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get(
        'engineering/{organization}/projects/{project}/qa-normative-v2',
        [EngineeringNormativeRegistryController::class, 'index']
    )->name('engineering.normative-v2.index');

    Route::post(
        'engineering/{organization}/projects/{project}/qa-normative-v2/scan',
        [EngineeringNormativeRegistryController::class, 'scan']
    )->name('engineering.normative-v2.scan');

    Route::post(
        'engineering/{organization}/projects/{project}/qa-normative-v2/run',
        [EngineeringNormativeRegistryController::class, 'runAgent']
    )->name('engineering.normative-v2.run');

    Route::post(
        'engineering/{organization}/projects/{project}/qa-normative-v2/inspections/{inspection}/suggest',
        [EngineeringNormativeRegistryController::class, 'suggest']
    )->name('engineering.normative-v2.suggest');

    Route::post(
        'engineering/{organization}/projects/{project}/qa-normative-v2/links/{link}/validate',
        [EngineeringNormativeRegistryController::class, 'validateLink']
    )->name('engineering.normative-v2.links.validate');

    Route::post(
        'engineering/{organization}/projects/{project}/qa-normative-v2/links/{link}/reject',
        [EngineeringNormativeRegistryController::class, 'rejectLink']
    )->name('engineering.normative-v2.links.reject');
});
