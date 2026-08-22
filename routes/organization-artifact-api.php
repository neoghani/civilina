<?php

use App\Http\Controllers\Api\OrganizationArtifactController;
use Illuminate\Support\Facades\Route;

/*
 * Browser-facing tenant API:
 * - API prefix / JSON semantics come from routes/api.php;
 * - "web" supplies the authenticated session and CSRF protection;
 * - "auth" rejects unauthenticated access.
 */
Route::middleware([
    'web',
    'auth',
])->group(
    function (): void {
        Route::get(
            '/organizations/{organization:public_id}/artifacts',
            [
                OrganizationArtifactController::class,
                'index',
            ]
        );

        Route::post(
            '/organizations/{organization:public_id}/artifacts',
            [
                OrganizationArtifactController::class,
                'store',
            ]
        );

        Route::get(
            '/organizations/{organization:public_id}/artifacts/{artifact:public_id}',
            [
                OrganizationArtifactController::class,
                'show',
            ]
        );
    }
);
