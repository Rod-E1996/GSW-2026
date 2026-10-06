<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class CustomResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // El enlace apunta a la pantalla de React (SPA), no al Blade.
        $url = rtrim(config('app.frontend_url'), '/')
            . '/restablecer-contrasena?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject(Lang::get('Restablecimiento de Contraseña'))
            ->view('emails.reset_password', [
                'url' => $url,
                'user' => $notifiable
            ]);
    }
}
