<?php

namespace App\Jobs;

use App\Models\Notificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envia un correo de la reserva y deja en notificaciones si salio, cuantos intentos llevo y por que fallo.
 */
class EnviarNotificacion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    //Segundos de espera antes del segundo y del tercer intento
    public $backoff = [60, 300];

    public function __construct(public Notificacion $notificacion, public Mailable $correo)
    {
    }

    public function handle(): void
    {
        $this->notificacion->increment('intentos');

        try {
            Mail::to($this->notificacion->destinatario)->send($this->correo);
        } catch (Throwable $e) {
            $this->notificacion->update(['error' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }

        $this->notificacion->update([
            'estado_envio' => Notificacion::ENVIADO,
            'enviado_at' => now(),
            'error' => null,
        ]);
    }

    //Agotados los intentos
    public function failed(Throwable $e): void
    {
        $this->notificacion->update([
            'estado_envio' => Notificacion::FALLIDO,
            'error' => mb_substr($e->getMessage(), 0, 2000),
        ]);
    }
}
