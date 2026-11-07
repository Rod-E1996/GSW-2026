<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerificarCorreoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Verifica tu correo')
            ->view('emails.verificar_correo', [
                'url' => self::enlace($notifiable),
                'user' => $notifiable,
            ]);
    }

    /**
     * El enlace abre la pantalla de React, que reenvia los datos a la API.
     * La firma es relativa: no depende del dominio por el que entre la peticion.
     */
    public static function enlace($notifiable): string
    {
        $firmada = URL::temporarySignedRoute(
            'api.correo.verificar',
            now()->addMinutes(config('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
            false
        );

        parse_str((string) parse_url($firmada, PHP_URL_QUERY), $firma);

        return rtrim(config('app.frontend_url'), '/') . '/verificar-correo?' . http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $firma['expires'],
            'signature' => $firma['signature'],
        ]);
    }
}
