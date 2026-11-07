<?php

namespace App\Mail;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Base de los correos de la reserva. No se encolan por si mismos: los envia
 * el trabajo EnviarNotificacion, que registra el resultado en notificaciones.
 */
abstract class CorreoReserva extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reserva $reserva)
    {
    }

    abstract public function asunto(): string;

    abstract protected function vista(): string;

    protected function datos(): array
    {
        return [];
    }

    public function build()
    {
        $this->reserva->loadMissing(['huesped', 'tipoHabitacion', 'habitacion']);

        return $this->subject($this->asunto())
            ->view($this->vista(), $this->datos() + [
                'reserva' => $this->reserva,
                'hotel' => config('hotel.nombre'),
                'enlace' => rtrim(config('app.frontend_url'), '/') . '/mi-cuenta/reservas/' . $this->reserva->codigo,
            ]);
    }
}
