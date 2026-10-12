<?php

use App\Http\Controllers\EngineeringEntryController;
use App\Http\Controllers\EngineeringWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
])
    ->group(function (): void {
        Route::get(
            '/engineering',
            EngineeringEntryController::class
        )
            ->name('engineering.entry');

        Route::get(
            '/engineering/{organization:public_id}/projects/{project}/workspace',
            [
                EngineeringWorkspaceController::class,
                'show',
            ]
        )
            ->name('engineering.workspace');
    });
