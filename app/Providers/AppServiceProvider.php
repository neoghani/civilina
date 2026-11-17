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
