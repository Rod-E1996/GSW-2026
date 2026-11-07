<?php

namespace App\Mail;

use App\Services\ComprobanteService;

/**
 * Anticipo aprobado: la reserva quedo confirmada. Lleva el comprobante PDF adjunto.
 */
class ReservaConfirmadaMail extends CorreoReserva
{
    public function asunto(): string
    {
        return 'Reserva ' . $this->reserva->codigo . ' confirmada';
    }

    protected function vista(): string
    {
        return 'emails.reserva_confirmada';
    }

    protected function datos(): array
    {
        return [
            'pagado' => $this->reserva->montoPagado(),
            'comprobante' => $this->reserva->comprobanteVigente()->first(),
        ];
    }

    public function build()
    {
        parent::build();

        $comprobante = $this->reserva->comprobanteVigente()->first();
        if ($comprobante) {
            $this->attachData(
                app(ComprobanteService::class)->pdf($comprobante),
                'comprobante-' . $this->reserva->codigo . '.pdf',
                ['mime' => 'application/pdf']
            );
        }

        return $this;
    }
}
