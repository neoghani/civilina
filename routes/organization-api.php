<?php

use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationMembershipController;
use Illuminate\Support\Facades\Route;

/*
 * These routes are loaded from routes/api.php, so they already inherit
 * the "api" middleware group and /api prefix.
 *
 * The additional "web" middleware is intentional: browser users
 * authenticate through Laravel's session guard. It provides encrypted
 * cookies, session state and CSRF protection, while "auth" enforces the
 * authenticated user.
 */
Route::middleware([
    'web',
    'auth',
])->group(
    function (): void {
        Route::get(
            '/organizations',
            [
                OrganizationController::class,
                'index',
            ]
        );

        Route::get(
            '/organizations/{organization:public_id}',
            [
                OrganizationController::class,
                'show',
            ]
        );

        Route::get(
            '/organizations/{organization:public_id}/members',
            [
                OrganizationMembershipController::class,
                'index',
            ]
        );

        Route::post(
            '/organizations/{organization:public_id}/members',
            [
                OrganizationMembershipController::class,
                'store',
            ]
        );
    }
);
