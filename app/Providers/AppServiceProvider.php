<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Request limits (per account, per IP before login). "app" covers
        // every web/API request; "engineering-heavy" the agent runs, matrix
        // generation and evidence uploads.
        \Illuminate\Support\Facades\RateLimiter::for(
            'app',
            static fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(
                (int) config('civilina.rate_limits.app', 300)
            )->by($request->user()?->getAuthIdentifier() ?? $request->ip())
        );
        \Illuminate\Support\Facades\RateLimiter::for(
            'engineering-heavy',
            static fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(
                (int) config('civilina.rate_limits.heavy', 30)
            )->by($request->user()?->getAuthIdentifier() ?? $request->ip())
        );

        // @civilinastaff … @endcivilinastaff: controls reserved to Civilina
        // platform staff (shared normative corpus management).
        \Illuminate\Support\Facades\Blade::if(
            'civilinastaff',
            static fn (): bool => \App\Http\Middleware\EnsureCivilinaPlatformStaff::isStaff(auth()->user()?->email)
        );

        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(
            static fn (object $notifiable, string $token) => (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Réinitialisation de votre mot de passe CIVILINA')
                ->greeting('Bonjour,')
                ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte CIVILINA.')
                ->action('Choisir un nouveau mot de passe', route('password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]))
                ->line('Ce lien est valable 60 minutes.')
                ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.')
                ->salutation('L’équipe Civilina')
        );
    }
}
