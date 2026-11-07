<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Politica de cancelacion definida en Configuracion. El plazo se cuenta hasta la hora
 * de entrada del dia de llegada; fuera de plazo se aplica el porcentaje de no presentacion.
 */
class CancelacionService
{
    public function __construct(
        private ComprobanteService $comprobantes,
        private NotificacionService $notificaciones
    ) {
    }

    public function evaluar(Reserva $reserva): array
    {
        $entrada = Carbon::parse($reserva->fecha_llegada->toDateString() . ' ' . config('hotel.checkin'));
        $limite = (clone $entrada)->subHours((int) Configuracion::valor('cancelacion_limite_horas', 48));
        $enPlazo = now()->lt($limite);

        $porcentaje = $enPlazo
            ? Configuracion::valor('cancelacion_reembolso_porcentaje', 100)
            : Configuracion::valor('no_show_reembolso_porcentaje', 0);

        $pagado = $this->anticipoPagado($reserva);

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

    /**
     * Cancela con la fila bloqueada: dos clics seguidos no la cancelan dos veces.
     * Recepcion tambien puede cancelar despues de la hora de entrada (huesped que no llego):
     * entonces se aplica el porcentaje de no presentacion.
     */
    public function cancelar(Reserva $reserva, ?string $motivo, bool $porRecepcion = false): array
    {
        $resultado = DB::transaction(function () use ($reserva, $motivo, $porRecepcion) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();
            $politica = $this->evaluar($reserva);

            $estadoCancelable = in_array((int) $reserva->estado_reserva, [Reserva::PENDIENTE, Reserva::CONFIRMADA]);
            if (!($politica['permitida'] || ($porRecepcion && $estadoCancelable))) {
                throw ValidationException::withMessages(['reserva' => $politica['motivo_no_permitida']]);
            }

            $reserva->comentarioHistorial = ($porRecepcion ? 'Cancelada en recepción. ' : 'Cancelada por el huésped. ')
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

            //El QR del comprobante deja de valer en recepcion
            $this->comprobantes->anular($reserva);

            return ['reserva' => $reserva, 'politica' => $politica];
        });

        //La cancelacion ya quedo guardada: un correo que falla no la deshace
        $this->notificaciones->cancelacion($resultado['reserva'], $resultado['politica']);

        return $resultado;
    }

    /**
     * Lo pagado son los pagos aprobados. Las reservas confirmadas antes de que existiera
     * la tabla pagos no tienen registros: para ellas se asume el anticipo pagado.
     */
    private function anticipoPagado(Reserva $reserva): float
    {
        $pagos = $reserva->pagos()->get();

        if ($pagos->isNotEmpty()) {
            //Un cobro que no pudo confirmar la reserva se devuelve completo, fuera de esta politica
            return round((float) $pagos
                ->where('estado_pago', Pago::APROBADO)
                ->reject(fn (Pago $p) => PagoService::requiereReembolso($p))
                ->sum('monto'), 2);
        }

        return in_array((int) $reserva->estado_reserva, [Reserva::CONFIRMADA, Reserva::EN_ESTADIA, Reserva::FINALIZADA])
            ? (float) $reserva->anticipo_requerido
            : 0.0;
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
