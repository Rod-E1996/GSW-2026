<?php

namespace App\Services;

use App\Mail\ReservaCanceladaMail;
use App\Models\Configuracion;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Politica de cancelacion definida en Configuracion. El plazo se cuenta hasta la hora
 * de entrada del dia de llegada; fuera de plazo se aplica el porcentaje de no presentacion.
 */
class CancelacionService
{
    public function evaluar(Reserva $reserva): array
    {
        $entrada = Carbon::parse($reserva->fecha_llegada->toDateString() . ' ' . config('hotel.checkin'));
        $limite = (clone $entrada)->subHours((int) Configuracion::valor('cancelacion_limite_horas', 48));
        $enPlazo = now()->lt($limite);

        $porcentaje = $enPlazo
            ? Configuracion::valor('cancelacion_reembolso_porcentaje', 100)
            : Configuracion::valor('no_show_reembolso_porcentaje', 0);

        //Una reserva se confirma al pagar el anticipo; la pendiente aun no pago nada
        $pagado = (int) $reserva->estado_reserva === Reserva::CONFIRMADA ? (float) $reserva->anticipo_requerido : 0.0;

        return [
            'permitida' => in_array((int) $reserva->estado_reserva, [Reserva::PENDIENTE, Reserva::CONFIRMADA]) && now()->lt($entrada),
            'motivo_no_permitida' => $this->motivoNoPermitida($reserva, $entrada),
            'limite_reembolso' => $limite->toIso8601String(),
            'en_plazo' => $enPlazo,
            'porcentaje_reembolso' => $porcentaje,
            'anticipo_pagado' => $pagado,
            'reembolso' => round($pagado * $porcentaje / 100, 2),
        ];
    }

    //Cancela con la fila bloqueada: dos clics seguidos no la cancelan dos veces.
    public function cancelar(Reserva $reserva, ?string $motivo): array
    {
        $resultado = DB::transaction(function () use ($reserva, $motivo) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();
            $politica = $this->evaluar($reserva);

            if (!$politica['permitida']) {
                throw ValidationException::withMessages(['reserva' => $politica['motivo_no_permitida']]);
            }

            $reserva->comentarioHistorial = 'Cancelada por el huésped. '
                . ($politica['anticipo_pagado'] > 0
                    ? 'Reembolso: ' . $this->numero($politica['porcentaje_reembolso']) . ' % del anticipo pagado ($'
                        . number_format($politica['reembolso'], 2) . ').'
                    : 'Sin anticipo pagado, no hay reembolso.')
                . ($motivo ? " Motivo: $motivo" : '');

            $reserva->update([
                'estado_reserva' => Reserva::CANCELADA,
                'cancelada_at' => now(),
                'motivo_cancelacion' => $motivo,
            ]);

            return ['reserva' => $reserva, 'politica' => $politica];
        });

        $correo = $resultado['reserva']->huesped->email ?? $resultado['reserva']->user?->email;
        //La cancelacion ya quedo guardada: un correo que falla no debe deshacerla
        try {
            if ($correo) {
                Mail::to($correo)->queue(new ReservaCanceladaMail($resultado['reserva'], $resultado['politica']));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $resultado;
    }

    private function motivoNoPermitida(Reserva $reserva, Carbon $entrada): ?string
    {
        return match (true) {
            (int) $reserva->estado_reserva === Reserva::CANCELADA => 'La reserva ya está cancelada.',
            (int) $reserva->estado_reserva === Reserva::EN_ESTADIA => 'La estadía ya comenzó. Habla con recepción.',
            (int) $reserva->estado_reserva === Reserva::FINALIZADA => 'La estadía ya terminó.',
            now()->gte($entrada) => 'Ya pasó la hora de entrada. Comunícate con el hotel para cancelar.',
            default => null,
        };
    }

    private function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
