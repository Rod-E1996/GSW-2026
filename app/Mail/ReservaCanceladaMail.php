<?php

namespace App\Mail;

use App\Models\Reserva;

/**
 * Confirmacion al huesped de que su reserva quedo cancelada, con el reembolso aplicado.
 */
class ReservaCanceladaMail extends CorreoReserva
{
    public function __construct(Reserva $reserva, public array $politica)
    {
        parent::__construct($reserva);
    }

    public function asunto(): string
    {
        return 'Reserva ' . $this->reserva->codigo . ' cancelada';
    }

    protected function vista(): string
    {
        return 'emails.reserva_cancelada';
    }

    protected function datos(): array
    {
        return ['politica' => $this->politica];
    }
}
