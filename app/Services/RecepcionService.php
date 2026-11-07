<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Habitacion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operacion de recepcion sobre una reserva: confirmar con el anticipo recibido,
 * registrar la entrada, cargar consumos y registrar la salida con el saldo cobrado.
 * Cada paso bloquea la reserva para que dos recepcionistas no la procesen a la vez.
 */
class RecepcionService
{
    const METODOS = [
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta (POS)',
        'transferencia' => 'Transferencia',
    ];

    public function __construct(
        private PagoService $pagos,
        private DisponibilidadService $disponibilidad,
        private ComprobanteService $comprobantes,
        private NotificacionService $notificaciones
    ) {
    }

    /**
     * Confirma una reserva pendiente registrando el anticipo recibido en recepcion.
     * $pago: monto, metodo_pago y, opcional, referencia (numero de transferencia o voucher).
     */
    public function confirmar(Reserva $reserva, array $pago, User $user): Reserva
    {
        $reserva = DB::transaction(function () use ($reserva, $pago, $user) {
            Habitacion::where('tipo_habitacion_id', $reserva->tipo_habitacion_id)->orderBy('id')->lockForUpdate()->get();
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            if ((int) $reserva->estado_reserva !== Reserva::PENDIENTE) {
                $this->rechazar('Solo se confirman reservas pendientes.');
            }
            if (!$this->pagos->sigueApartada($reserva)) {
                $this->rechazar('La reserva venció y la habitación ya no está libre para esas fechas. Modifícala o registra una nueva.');
            }

            $monto = round((float) $pago['monto'], 2);
            if ($monto + 0.001 < (float) $reserva->anticipo_requerido) {
                $this->rechazar('El monto no cubre el anticipo de $' . number_format((float) $reserva->anticipo_requerido, 2) . '.', 'monto');
            }
            if ($monto - 0.001 > (float) $reserva->total) {
                $this->rechazar('El monto supera el total de la reserva.', 'monto');
            }

            $this->registrarPago($reserva, Pago::ANTICIPO, $monto, $pago, $user);

            $reserva->comentarioHistorial = 'Confirmada en recepción con anticipo de $' . number_format($monto, 2)
                . ' (' . self::METODOS[$pago['metodo_pago']] . ').';
            $reserva->update(['estado_reserva' => Reserva::CONFIRMADA]);

            return $reserva;
        }, ReservaService::INTENTOS);

        try {
            $this->comprobantes->emitir($reserva);
        } catch (\Throwable $e) {
            report($e);
        }
        $this->notificaciones->confirmacion($reserva);

        return $reserva->fresh();
    }

    /**
     * Registra la entrada. $habitacionId permite asignar o cambiar la habitacion
     * (por ejemplo, si la reservada esta en limpieza); se revalida para toda la estadia.
     */
    public function checkin(Reserva $reserva, ?int $habitacionId, User $user): Reserva
    {
        return DB::transaction(function () use ($reserva, $habitacionId) {
            $destino = Habitacion::with('tipoHabitacion')->where('estado', 1)->find($habitacionId ?? $reserva->habitacion_id);
            if (!$destino) {
                $this->rechazar('Asigna una habitación para registrar la entrada.', 'habitacion_id');
            }

            Habitacion::whereIn('tipo_habitacion_id', array_unique([$reserva->tipo_habitacion_id, $destino->tipo_habitacion_id]))
                ->orderBy('id')->lockForUpdate()->get();
            $reserva = Reserva::with('habitacion')->whereKey($reserva->id)->lockForUpdate()->firstOrFail();
            $hoy = now()->startOfDay();

            match (true) {
                (int) $reserva->estado_reserva === Reserva::PENDIENTE => $this->rechazar('La reserva está pendiente: confirma primero el anticipo.'),
                (int) $reserva->estado_reserva !== Reserva::CONFIRMADA => $this->rechazar('Solo se registra la entrada de reservas confirmadas.'),
                $hoy->lt($reserva->fecha_llegada) => $this->rechazar('La llegada es el ' . $reserva->fecha_llegada->format('d/m/Y') . '. Si el huésped llega antes, modifica primero las fechas.'),
                $hoy->gte($reserva->fecha_salida) => $this->rechazar('La fecha de salida ya pasó. Modifica la reserva o cancélala.'),
                default => null,
            };

            $personas = (int) $reserva->adultos + (int) $reserva->ninos;
            if ($destino->tipoHabitacion->capacidad < $personas) {
                $this->rechazar("La habitación {$destino->numero} admite hasta {$destino->tipoHabitacion->capacidad} persona(s).", 'habitacion_id');
            }
            if ($destino->estado_habitacion !== Habitacion::DISPONIBLE) {
                $this->rechazar("La habitación {$destino->numero} está en {$destino->estado_nombre}. Elige otra o cambia su estado en Habitaciones.", 'habitacion_id');
            }
            if (!$this->disponibilidad->sePuedeReservar($destino, $reserva->fecha_llegada->toDateString(), $reserva->fecha_salida->toDateString(), $reserva->id)) {
                $this->rechazar("La habitación {$destino->numero} está ocupada o bloqueada en esas fechas.", 'habitacion_id');
            }

            $cambio = $reserva->habitacion_id && (int) $reserva->habitacion_id !== (int) $destino->id
                ? ' (antes ' . $reserva->habitacion?->numero . ')' : '';

            $reserva->comentarioHistorial = "Entrada registrada en la habitación {$destino->numero}{$cambio}.";
            $reserva->update([
                'habitacion_id' => $destino->id,
                'tipo_habitacion_id' => $destino->tipo_habitacion_id,
                'estado_reserva' => Reserva::EN_ESTADIA,
                'check_in_at' => now(),
            ]);
            $reserva->noches()->update(['habitacion_id' => $destino->id]);

            //Recepcion vio el comprobante al recibir al huesped
            $comprobante = $reserva->comprobanteVigente()->first();
            if ($comprobante && !$comprobante->verificado_at) {
                $comprobante->update(['verificado_at' => now(), 'verificado_por' => auth()->id()]);
            }

            return $reserva->fresh();
        }, ReservaService::INTENTOS);
    }

    //Consumo cargado durante la estadia: cantidad por precio actual del catalogo.
    public function agregarConsumo(Reserva $reserva, array $consumo): Reserva
    {
        return DB::transaction(function () use ($reserva, $consumo) {
            $reserva = $this->enEstadia($reserva);
            $servicio = Servicio::where('estado', 1)->find($consumo['servicio_id']);
            if (!$servicio) {
                $this->rechazar('Ese servicio no está disponible.', 'servicio_id');
            }

            $cantidad = (int) $consumo['cantidad'];
            $subtotal = round($cantidad * (float) $servicio->precio, 2);

            $reserva->servicios()->attach($servicio->id, [
                'cantidad' => $cantidad,
                'precio_unitario' => $servicio->precio,
                'subtotal' => $subtotal,
                'fecha_servicio' => now()->toDateString(),
                'estado_servicio' => Reserva::SERVICIO_CONSUMIDO,
                'notas' => $consumo['notas'] ?? null,
            ]);

            return $this->recalcular($reserva, "Consumo cargado: {$servicio->nombre} × {$cantidad} ($" . number_format($subtotal, 2) . ').');
        });
    }

    //Solo se quitan consumos cargados en la estadia; lo contratado al reservar se cambia modificando la reserva.
    public function quitarConsumo(Reserva $reserva, int $lineaId): Reserva
    {
        return DB::transaction(function () use ($reserva, $lineaId) {
            $reserva = $this->enEstadia($reserva);
            $linea = DB::table('reserva_servicio')
                ->join('servicios', 'servicios.id', '=', 'reserva_servicio.servicio_id')
                ->where('reserva_servicio.id', $lineaId)
                ->where('reserva_servicio.reserva_id', $reserva->id)
                ->first(['reserva_servicio.*', 'servicios.nombre']);

            if (!$linea || (int) $linea->estado_servicio !== Reserva::SERVICIO_CONSUMIDO) {
                $this->rechazar('Solo se quitan consumos cargados durante la estadía.', 'linea');
            }

            DB::table('reserva_servicio')->where('id', $lineaId)->delete();

            return $this->recalcular($reserva, "Consumo quitado: {$linea->nombre} × {$linea->cantidad} ($" . number_format((float) $linea->subtotal, 2) . ').');
        });
    }

    /**
     * Registra la salida: cobra el saldo (si queda), finaliza la reserva y deja la
     * habitacion en limpieza. $pago: monto y metodo_pago, obligatorios si hay saldo.
     */
    public function checkout(Reserva $reserva, array $pago, User $user): Reserva
    {
        return DB::transaction(function () use ($reserva, $pago, $user) {
            $reserva = $this->enEstadia($reserva);
            $saldo = $this->saldo($reserva);
            $cobro = '';

            if ($saldo > 0) {
                if (empty($pago['metodo_pago']) || !isset($pago['monto'])) {
                    $this->rechazar('Registra el pago del saldo de $' . number_format($saldo, 2) . '.', 'monto');
                }
                if (abs(round((float) $pago['monto'], 2) - $saldo) > 0.001) {
                    $this->rechazar('El pago debe ser igual al saldo pendiente: $' . number_format($saldo, 2) . '.', 'monto');
                }

                $this->registrarPago($reserva, Pago::SALDO, $saldo, $pago, $user);
                $cobro = ' Saldo cobrado: $' . number_format($saldo, 2) . ' (' . self::METODOS[$pago['metodo_pago']] . ').';
            }

            $reserva->comentarioHistorial = 'Salida registrada.' . $cobro;
            $reserva->update(['estado_reserva' => Reserva::FINALIZADA, 'check_out_at' => now()]);

            //La habitacion queda libre para el inventario y pasa a limpieza
            Habitacion::whereKey($reserva->habitacion_id)->update(['estado_habitacion' => Habitacion::LIMPIEZA]);

            return $reserva->fresh();
        }, ReservaService::INTENTOS);
    }

    public function saldo(Reserva $reserva): float
    {
        return max(0, round((float) $reserva->total - $reserva->montoPagado(), 2));
    }

    private function enEstadia(Reserva $reserva): Reserva
    {
        $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

        if ((int) $reserva->estado_reserva !== Reserva::EN_ESTADIA) {
            $this->rechazar('La reserva no está en estadía.');
        }

        return $reserva;
    }

    //Recalcula servicios, impuesto y total despues de cargar o quitar un consumo
    private function recalcular(Reserva $reserva, string $comentario): Reserva
    {
        $servicios = round((float) DB::table('reserva_servicio')->where('reserva_id', $reserva->id)->sum('subtotal'), 2);
        $base = (float) $reserva->subtotal_alojamiento + $servicios - (float) $reserva->descuento;
        $impuesto = round($base * Configuracion::valor('impuesto_porcentaje') / 100, 2);

        $reserva->update([
            'subtotal_servicios' => $servicios,
            'impuesto' => $impuesto,
            'total' => round($base + $impuesto, 2),
        ]);

        $reserva->historial()->create([
            'estado_anterior' => $reserva->estado_reserva,
            'estado_nuevo' => $reserva->estado_reserva,
            'comentario' => mb_substr($comentario, 0, 255),
            'user_id' => auth()->id(),
        ]);

        return $reserva->fresh();
    }

    private function registrarPago(Reserva $reserva, string $tipo, float $monto, array $pago, User $user): Pago
    {
        $referencia = isset($pago['referencia']) && trim($pago['referencia']) !== '' ? trim($pago['referencia']) : null;

        if ($referencia && Pago::where('referencia_externa', $referencia)->exists()) {
            $this->rechazar('Ya hay un pago registrado con esa referencia.', 'referencia');
        }

        return $reserva->pagos()->create([
            'tipo_pago' => $tipo,
            'metodo_pago' => $pago['metodo_pago'],
            'pasarela' => null,
            'referencia_externa' => $referencia,
            'monto' => $monto,
            'estado_pago' => Pago::APROBADO,
            'respuesta_pasarela' => null,
            'pagado_at' => now(),
            'user_id' => $user->id,
        ]);
    }

    private function rechazar(string $mensaje, string $campo = 'reserva'): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
