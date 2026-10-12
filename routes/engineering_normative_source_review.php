<?php

use App\Http\Controllers\EngineeringNormativeSourceReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('engineering/{organization}/projects/{project}/normative-source-review')
    ->name('engineering.normative-source-review.')
    ->group(function () {
        Route::get('/', [EngineeringNormativeSourceReviewController::class, 'index'])->name('index');
        Route::get('/{document}', [EngineeringNormativeSourceReviewController::class, 'show'])
            ->whereNumber('document')
            ->name('show');
        Route::post('/{document}/decision', [EngineeringNormativeSourceReviewController::class, 'decide'])
            ->whereNumber('document')
            ->name('decision');
    });
