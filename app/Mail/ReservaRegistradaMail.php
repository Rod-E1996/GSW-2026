<?php

namespace App\Mail;

use App\Models\Configuracion;

/**
 * La reserva quedo registrada y apartada mientras se paga el anticipo.
 */
class ReservaRegistradaMail extends CorreoReserva
{
    public function asunto(): string
    {
        return 'Reserva ' . $this->reserva->codigo . ' registrada: falta el anticipo';
    }

    protected function vista(): string
    {
        return 'emails.reserva_registrada';
    }

    protected function datos(): array
    {
        return [
            'vence' => $this->reserva->created_at->copy()->addHours((int) Configuracion::valor('reserva_pendiente_horas', 24)),
        ];
    }
}
