<?php

namespace App\Services;

use App\Jobs\EnviarNotificacion;
use App\Mail\CorreoReserva;
use App\Mail\ReservaCanceladaMail;
use App\Mail\ReservaConfirmadaMail;
use App\Mail\ReservaModificadaMail;
use App\Mail\ReservaRegistradaMail;
use App\Models\Notificacion;
use App\Models\Reserva;

/**
 * Correos de la reserva: cada uno queda en notificaciones y se envia en cola.
 * Un correo que falla nunca deshace la operacion que lo origino.
 */
class NotificacionService
{
    public function registro(Reserva $reserva): ?Notificacion
    {
        return $this->enviar($reserva, Notificacion::REGISTRO, new ReservaRegistradaMail($reserva));
    }

    public function confirmacion(Reserva $reserva): ?Notificacion
    {
        return $this->enviar($reserva, Notificacion::CONFIRMACION, new ReservaConfirmadaMail($reserva));
    }

    //$cambios: [['campo' => 'Llegada', 'antes' => '...', 'despues' => '...'], ...]
    public function modificacion(Reserva $reserva, array $cambios): ?Notificacion
    {
        return $this->enviar($reserva, Notificacion::MODIFICACION, new ReservaModificadaMail($reserva, $cambios));
    }

    public function cancelacion(Reserva $reserva, array $politica): ?Notificacion
    {
        return $this->enviar($reserva, Notificacion::CANCELACION, new ReservaCanceladaMail($reserva, $politica));
    }

    private function enviar(Reserva $reserva, string $tipo, CorreoReserva $correo): ?Notificacion
    {
        $reserva->loadMissing('huesped');
        $destinatario = $reserva->huesped?->email ?: $reserva->huesped?->user?->email;

        if (!$destinatario) {
            return null;
        }

        try {
            $notificacion = Notificacion::create([
                'reserva_id' => $reserva->id,
                'user_id' => $reserva->huesped?->user_id,
                'tipo' => $tipo,
                'canal' => Notificacion::CANAL_CORREO,
                'destinatario' => $destinatario,
                'asunto' => mb_substr($correo->asunto(), 0, 200),
                'estado_envio' => Notificacion::PENDIENTE,
                'intentos' => 0,
            ]);

            EnviarNotificacion::dispatch($notificacion, $correo);

            return $notificacion->fresh();
        } catch (\Throwable $e) {
            report($e);

            return isset($notificacion) ? $notificacion->fresh() : null;
        }
    }
}
