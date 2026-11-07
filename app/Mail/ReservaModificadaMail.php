<?php

namespace App\Mail;

use App\Models\Reserva;

/**
 * Aviso de cambios en la reserva (fechas, habitacion, servicios...).
 */
class ReservaModificadaMail extends CorreoReserva
{
    //$cambios: [['campo' => 'Llegada', 'antes' => '...', 'despues' => '...'], ...]
    public function __construct(Reserva $reserva, public array $cambios)
    {
        parent::__construct($reserva);
    }

    public function asunto(): string
    {
        return 'Reserva ' . $this->reserva->codigo . ' modificada';
    }

    protected function vista(): string
    {
        return 'emails.reserva_modificada';
    }

    protected function datos(): array
    {
        return ['cambios' => $this->cambios];
    }
}
