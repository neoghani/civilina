<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation e-mail for a pilot engineering firm: link to choose the
 * password. Sent synchronously (no queue) so the operator sees failures.
 */
class CivilinaInvitation extends Notification
{
    public function __construct(
        public readonly string $url,
        public readonly string $organizationName,
        public readonly int $expireHours
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre accès pilote CIVILINA Engineering')
            ->greeting('Bonjour '.($notifiable->name ?? '').',')
            ->line('Un espace CIVILINA Engineering a été ouvert pour « '.$this->organizationName.' » dans le cadre du programme pilote.')
            ->line('Pour activer votre compte, choisissez votre mot de passe :')
            ->action('Activer mon compte', $this->url)
            ->line('Ce lien est personnel et valable '.$this->expireHours.' heures. Passé ce délai, utilisez « Mot de passe oublié » sur la page de connexion.')
            ->line('Si vous n’attendiez pas ce message, ignorez-le.')
            ->salutation('L’équipe Civilina');
    }
}
