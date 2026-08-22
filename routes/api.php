<?php

use App\Http\Controllers\Api\ArtifactController;
use Illuminate\Support\Facades\Route;

Route::prefix('artifacts')
    ->name('artifacts.')
    ->group(function (): void {
        Route::post(
            '/',
            [ArtifactController::class, 'store']
        )->name('store');

        Route::get(
            '/{artifact}',
            [ArtifactController::class, 'show']
        )->name('show');

        Route::get(
            '/{artifact}/revisions',
            [ArtifactController::class, 'revisions']
        )->name('revisions');

        Route::post(
            '/{artifact}/revisions',
            [ArtifactController::class, 'revise']
        )->name('revise');

        Route::post(
            '/{artifact}/transitions',
            [ArtifactController::class, 'transition']
        )->name('transition');
    });
require __DIR__.'/organization-api.php';

require __DIR__.'/organization-artifact-api.php';
