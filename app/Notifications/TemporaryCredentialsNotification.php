<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemporaryCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $temporaryPassword,
        private readonly string $roleLabel,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = rtrim(
            (string) config('app.url'),
            '/'
        ).route('login', [], false);

        return (new MailMessage())
            ->subject('Tu acceso temporal a SchoolPass')
            ->greeting('Hola, '.$notifiable->name)
            ->line(
                'La institución creó o restableció tu cuenta de acceso a SchoolPass.'
            )
            ->line('Perfil asignado: '.$this->roleLabel)
            ->line('Correo de acceso: '.$notifiable->email)
            ->line('Contraseña temporal: '.$this->temporaryPassword)
            ->action('Iniciar sesión', $loginUrl)
            ->line(
                'Por seguridad, deberás cambiar esta contraseña después de iniciar sesión.'
            )
            ->line(
                'Si no reconoces esta asignación, comunícate con la administración de tu institución.'
            )
            ->salutation('Equipo SchoolPass');
    }
}