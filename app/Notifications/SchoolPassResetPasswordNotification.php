<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolPassResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {
    }

    public function via(
        object $notifiable,
    ): array {
        return ['mail'];
    }

    public function toMail(
        object $notifiable,
    ): MailMessage {
        $broker = (string) config(
            'auth.defaults.passwords',
            'users'
        );

        $expiresIn = (int) config(
            "auth.passwords.{$broker}.expire",
            60
        );

        /*
         * Se genera una ruta relativa y después se une
         * expresamente con APP_URL. Así evitamos que un
         * acceso local produzca enlaces hacia 127.0.0.1.
         */
        $relativeUrl = route(
            'password.reset',
            [
                'token' => $this->token,
                'email' => $notifiable
                    ->getEmailForPasswordReset(),
            ],
            false
        );

        $url = rtrim(
            (string) config('app.url'),
            '/'
        ).$relativeUrl;

        return (new MailMessage())
            ->subject(
                'Restablece tu acceso a SchoolPass'
            )
            ->greeting(
                'Hola, '.$notifiable->name
            )
            ->line(
                'Recibimos una solicitud para restablecer '
                .'la contraseña de tu cuenta administrativa.'
            )
            ->action(
                'Crear nueva contraseña',
                $url
            )
            ->line(
                "Este enlace vencerá en {$expiresIn} minutos."
            )
            ->line(
                'Si no realizaste esta solicitud, puedes '
                .'ignorar este mensaje.'
            )
            ->salutation(
                'Equipo SchoolPass'
            );
    }
}