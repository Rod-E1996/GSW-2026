<?php

namespace App\Mail;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmacion al huesped de que su reserva quedo cancelada, con el reembolso aplicado.
 */
class ReservaCanceladaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Reserva $reserva, public array $politica)
    {
    }

    public function build()
    {
        return $this->subject('Reserva ' . $this->reserva->codigo . ' cancelada')
            ->view('emails.reserva_cancelada', [
                'reserva' => $this->reserva,
                'politica' => $this->politica,
                'hotel' => config('hotel.nombre'),
            ]);
    }
}
