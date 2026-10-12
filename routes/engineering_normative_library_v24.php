<?php

use App\Http\Controllers\EngineeringNormativeLibraryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get(
        'engineering/{organization}/projects/{project}/normative-library',
        [EngineeringNormativeLibraryController::class, 'index']
    )->name('engineering.normative-library.index');

    Route::get(
        'engineering/{organization}/projects/{project}/normative-library/standards/{standard}',
        [EngineeringNormativeLibraryController::class, 'show']
    )->name('engineering.normative-library.show');

    Route::get(
        'engineering/{organization}/projects/{project}/normative-library/standards/{standard}/sources/{source}/inline',
        [EngineeringNormativeLibraryController::class, 'inline']
    )->name('engineering.normative-library.inline');

    Route::patch(
        'engineering/{organization}/projects/{project}/normative-library/standards/{standard}',
        [EngineeringNormativeLibraryController::class, 'updateStandard']
    )->name('engineering.normative-library.standard.update');

    Route::patch(
        'engineering/{organization}/projects/{project}/normative-library/requirements/{requirement}',
        [EngineeringNormativeLibraryController::class, 'updateRequirement']
    )->name('engineering.normative-library.requirement.update');
});
