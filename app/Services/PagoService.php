<?php

namespace App\Services;

use App\Models\Habitacion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaHistorial;
use App\Models\User;
use App\Pagos\Pasarela;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pago del anticipo. La reserva se confirma solo cuando la pasarela aprueba el pago,
 * y una misma notificacion recibida dos veces no registra ni confirma dos veces.
 */
class PagoService
{
    public function __construct(
        private Pasarela $pasarela,
        private DisponibilidadService $disponibilidad,
        private ComprobanteService $comprobantes,
        private NotificacionService $notificaciones
    ) {
    }

    /**
     * Abre un intento de pago y devuelve la URL de la pasarela. Si el huesped ya tiene
     * un intento abierto y reciente (doble clic, volver atras) se reutiliza.
     */
    public function iniciar(Reserva $reserva, User $user): array
    {
        $pago = DB::transaction(function () use ($reserva, $user) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            $this->validarQueSePuedePagar($reserva);

            $abierto = $reserva->pagos()
                ->where('estado_pago', Pago::PENDIENTE)
                ->where('pasarela', $this->pasarela->nombre())
                ->where('created_at', '>', now()->subMinutes(config('services.pasarela.intento_minutos', 30)))
                ->reorder('id', 'desc')
                ->first();

            if ($abierto && (float) $abierto->monto === (float) $reserva->anticipo_requerido) {
                return $abierto;
            }

            return $reserva->pagos()->create([
                'tipo_pago' => Pago::ANTICIPO,
                'metodo_pago' => 'tarjeta',
                'pasarela' => $this->pasarela->nombre(),
                'referencia_externa' => (string) Str::uuid(),
                'monto' => $reserva->anticipo_requerido,
                'estado_pago' => Pago::PENDIENTE,
                'user_id' => $user->id,
            ]);
        });

        return ['pago' => $pago, 'url' => $this->pasarela->urlDePago($pago)];
    }

    //Punto de entrada de la notificacion de la pasarela: valida la firma y la procesa.
    public function recibirNotificacion(string $cuerpo, ?string $firma): array
    {
        return $this->procesar($this->pasarela->leerNotificacion($cuerpo, $firma));
    }

    /**
     * $datos: referencia, aprobado, monto y respuesta (ver Pasarela::leerNotificacion).
     * Devuelve el pago, si la reserva quedo confirmada y si la notificacion era repetida.
     */
    public function procesar(array $datos): array
    {
        $tipoId = Pago::where('referencia_externa', $datos['referencia'])
            ->join('reservas', 'reservas.id', '=', 'pagos.reserva_id')
            ->value('reservas.tipo_habitacion_id');

        if ($tipoId === null) {
            throw ValidationException::withMessages(['referencia' => 'No existe un pago con esa referencia.']);
        }

        $resultado = DB::transaction(function () use ($datos, $tipoId) {
            //Mismo orden de bloqueo que ReservaService: primero las habitaciones del tipo, luego la reserva
            Habitacion::where('tipo_habitacion_id', $tipoId)->orderBy('id')->lockForUpdate()->get();

            $pago = Pago::where('referencia_externa', $datos['referencia'])->lockForUpdate()->firstOrFail();

            //Notificacion repetida: el pago ya tiene su resultado
            if ((int) $pago->estado_pago !== Pago::PENDIENTE) {
                return ['pago' => $pago, 'confirmada' => false, 'repetida' => true];
            }

            $reserva = Reserva::whereKey($pago->reserva_id)->lockForUpdate()->firstOrFail();
            $respuesta = $datos['respuesta'];
            $aprobado = $datos['aprobado'];

            //La pasarela debe cobrar exactamente lo que se le pidio
            if ($aprobado && round($datos['monto'], 2) !== round((float) $pago->monto, 2)) {
                $aprobado = false;
                $respuesta['motivo'] = 'El monto cobrado no coincide con el anticipo.';
                Log::warning('Pago con monto distinto al solicitado', ['referencia' => $pago->referencia_externa]);
            }

            $pago->update([
                'estado_pago' => $aprobado ? Pago::APROBADO : Pago::RECHAZADO,
                'respuesta_pasarela' => $respuesta,
                'pagado_at' => $aprobado ? now() : null,
            ]);

            if (!$aprobado) {
                return ['pago' => $pago, 'confirmada' => false, 'repetida' => false];
            }

            if ($this->sigueApartada($reserva)) {
                $reserva->comentarioHistorial = 'Anticipo de $' . number_format((float) $pago->monto, 2) . ' aprobado'
                    . (isset($respuesta['autorizacion']) ? ' (autorización ' . $respuesta['autorizacion'] . ')' : '') . '.';
                $reserva->update(['estado_reserva' => Reserva::CONFIRMADA]);

                return ['pago' => $pago, 'confirmada' => true, 'repetida' => false];
            }

            //Se cobro, pero la reserva ya no se puede confirmar: queda constancia para devolverlo
            $pago->update(['respuesta_pasarela' => $respuesta + ['hotel' => ['requiere_reembolso' => true]]]);
            ReservaHistorial::create([
                'reserva_id' => $reserva->id,
                'estado_anterior' => $reserva->estado_reserva,
                'estado_nuevo' => $reserva->estado_reserva,
                'comentario' => 'Pago de $' . number_format((float) $pago->monto, 2)
                    . ' aprobado sin poder confirmar la reserva: el hotel debe reembolsarlo.',
            ]);
            Log::warning('Pago aprobado de una reserva que ya no se puede confirmar', ['reserva' => $reserva->codigo]);

            return ['pago' => $pago, 'confirmada' => false, 'repetida' => false];
        }, ReservaService::INTENTOS);

        if ($resultado['confirmada']) {
            $reserva = $resultado['pago']->reserva;

            try {
                $this->comprobantes->emitir($reserva);
            } catch (\Throwable $e) {
                report($e);
            }

            $this->notificaciones->confirmacion($reserva);
        }

        return $resultado;
    }

    /**
     * Pago aprobado que no pudo confirmar su reserva (ya cancelada o sin habitacion):
     * el hotel debe devolverlo completo. Lo pagado antes de cancelar sigue la politica de cancelacion.
     */
    public static function requiereReembolso(Pago $pago): bool
    {
        return (int) $pago->estado_pago === Pago::APROBADO
            && !empty($pago->respuesta_pasarela['hotel']['requiere_reembolso']);
    }

    private function validarQueSePuedePagar(Reserva $reserva): void
    {
        $motivo = match (true) {
            (int) $reserva->estado_reserva === Reserva::CONFIRMADA => 'La reserva ya está confirmada: su anticipo ya se pagó.',
            (int) $reserva->estado_reserva === Reserva::CANCELADA => 'La reserva está cancelada.',
            (int) $reserva->estado_reserva !== Reserva::PENDIENTE => 'Esta reserva ya no admite pagos en línea.',
            $reserva->pagos()->aprobados()->exists() => 'El anticipo de esta reserva ya se pagó.',
            (float) $reserva->anticipo_requerido <= 0 => 'Esta reserva no requiere anticipo.',
            !$this->sigueApartada($reserva) => 'La habitación ya no está disponible para esas fechas. Haz una reserva nueva.',
            default => null,
        };

        if ($motivo) {
            throw ValidationException::withMessages(['reserva' => $motivo]);
        }
    }

    //Pendiente aun vigente, o vencida pero con la habitacion todavia libre
    private function sigueApartada(Reserva $reserva): bool
    {
        if ((int) $reserva->estado_reserva !== Reserva::PENDIENTE) {
            return false;
        }

        if (Reserva::vigentes()->whereKey($reserva->id)->exists()) {
            return true;
        }

        $habitacion = $reserva->habitacion()->first();

        return $habitacion !== null && $this->disponibilidad->sePuedeReservar(
            $habitacion,
            $reserva->fecha_llegada->toDateString(),
            $reserva->fecha_salida->toDateString()
        );
    }
}
