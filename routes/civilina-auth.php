<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordSetupController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(
    function (): void {
        Route::get(
            '/login',
            [
                AuthenticatedSessionController::class,
                'create',
            ]
        )->name('login');

        Route::post(
            '/login',
            [
                AuthenticatedSessionController::class,
                'store',
            ]
        )
            ->middleware(
                'throttle:10,1'
            )
            ->name(
                'login.store'
            );

        // Forgotten password (60 min link) and invitation (pilot accounts).
        Route::get('/forgot-password', [PasswordSetupController::class, 'requestForm'])
            ->name('password.request');
        Route::post('/forgot-password', [PasswordSetupController::class, 'sendResetLink'])
            ->middleware('throttle:5,1')
            ->name('password.email');
        Route::get('/reset-password/{token}', [PasswordSetupController::class, 'resetForm'])
            ->name('password.reset');
        Route::post('/reset-password', [PasswordSetupController::class, 'reset'])
            ->middleware('throttle:10,1')
            ->name('password.update');
        Route::get('/invitation/{token}', [PasswordSetupController::class, 'invitationForm'])
            ->name('invitation.create');
        Route::post('/invitation', [PasswordSetupController::class, 'acceptInvitation'])
            ->middleware('throttle:10,1')
            ->name('invitation.store');
    }
);

Route::post(
    '/logout',
    [
        AuthenticatedSessionController::class,
        'destroy',
    ]
)
    ->middleware('auth')
    ->name('logout');
