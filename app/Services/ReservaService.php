<?php

namespace App\Services;

use App\Exceptions\HabitacionNoDisponible;
use App\Models\Habitacion;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

/**
 * Crea reservas sin sobreventa. La revalidacion y la insercion ocurren en una sola
 * transaccion que bloquea las habitaciones del tipo: si dos solicitudes compiten,
 * la segunda espera a la primera y ya ve su reserva.
 */
class ReservaService
{
    //Reintentos si la base cancela la transaccion por un interbloqueo
    const INTENTOS = 5;

    public function __construct(
        private DisponibilidadService $disponibilidad,
        private CotizacionService $cotizaciones
    ) {
    }

    /**
     * $datos: habitacion_id, huesped_id, fecha_llegada, fecha_salida, adultos y,
     * opcionales, ninos, hora_estimada_llegada, canal, notas, user_id.
     * $servicios: los pedidos que entiende CotizacionService::calcular.
     *
     * @throws HabitacionNoDisponible si otra reserva o un bloqueo ocupo la habitacion
     */
    public function crear(array $datos, array $servicios = []): Reserva
    {
        $tipoId = Habitacion::whereKey($datos['habitacion_id'])->value('tipo_habitacion_id');

        return DB::transaction(function () use ($datos, $servicios, $tipoId) {
            //Siempre en el mismo orden, para que dos solicitudes no se esperen mutuamente
            $habitaciones = Habitacion::with('tipoHabitacion')
                ->where('tipo_habitacion_id', $tipoId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $habitacion = $habitaciones->firstWhere('id', $datos['habitacion_id']);

            if (!$habitacion || !$this->disponibilidad->sePuedeReservar($habitacion, $datos['fecha_llegada'], $datos['fecha_salida'])) {
                throw new HabitacionNoDisponible();
            }

            $cotizacion = $this->cotizaciones->calcular(
                $habitacion->tipoHabitacion,
                $datos['fecha_llegada'],
                $datos['fecha_salida'],
                (int) $datos['adultos'] + (int) ($datos['ninos'] ?? 0),
                $servicios
            );

            $reserva = Reserva::create($datos + array_intersect_key($cotizacion, array_flip([
                'subtotal_alojamiento', 'subtotal_servicios', 'descuento', 'impuesto', 'total', 'anticipo_requerido',
            ])) + [
                'codigo' => Reserva::generarCodigo(),
                'tipo_habitacion_id' => $tipoId,
                'estado_reserva' => Reserva::PENDIENTE,
            ]);

            $reserva->noches()->createMany(array_map(fn (array $noche) => [
                'habitacion_id' => $habitacion->id,
                'fecha' => $noche['fecha'],
                'temporada_id' => $noche['temporada_id'],
                'precio_noche' => $noche['precio_noche'],
            ], $cotizacion['noches']));

            foreach ($cotizacion['servicios'] as $linea) {
                $reserva->servicios()->attach($linea['servicio_id'], [
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'subtotal' => $linea['subtotal'],
                    'fecha_servicio' => $linea['fecha_servicio'],
                    'hora_servicio' => $linea['hora_servicio'],
                    'estado_servicio' => 1,
                ]);
            }

            return $reserva;
        }, self::INTENTOS);
    }

    /**
     * Cambia fechas, habitacion, personas o servicios de una reserva pendiente o confirmada.
     * Revalida la disponibilidad sin contar la propia reserva y recalcula los importes con
     * las tarifas vigentes. $servicios null conserva los servicios contratados.
     * Devuelve la lista de cambios ([campo, antes, despues]); vacia si no cambio nada.
     *
     * @throws HabitacionNoDisponible
     */
    public function modificar(Reserva $reserva, array $datos, ?array $servicios = null): array
    {
        $tipoNuevo = Habitacion::whereKey($datos['habitacion_id'])->value('tipo_habitacion_id');

        return DB::transaction(function () use ($reserva, $datos, $servicios, $tipoNuevo) {
            //Mismo orden de bloqueo que crear(): habitaciones (de ambos tipos) y luego la reserva
            Habitacion::whereIn('tipo_habitacion_id', array_unique([$reserva->tipo_habitacion_id, $tipoNuevo]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $reserva = Reserva::with(['habitacion', 'tipoHabitacion', 'servicios'])->whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            if (!in_array((int) $reserva->estado_reserva, [Reserva::PENDIENTE, Reserva::CONFIRMADA])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reserva' => 'Solo se modifican reservas pendientes o confirmadas.',
                ]);
            }

            $habitacion = Habitacion::with('tipoHabitacion')->findOrFail($datos['habitacion_id']);

            if (!$this->disponibilidad->sePuedeReservar($habitacion, $datos['fecha_llegada'], $datos['fecha_salida'], $reserva->id)) {
                throw new HabitacionNoDisponible();
            }

            $pedidos = $servicios ?? $reserva->servicios->map(fn ($s) => [
                'servicio_id' => $s->id,
                'cantidad' => (int) $s->pivot->cantidad,
                'fecha_servicio' => $s->pivot->fecha_servicio ? substr($s->pivot->fecha_servicio, 0, 10) : null,
                'hora_servicio' => $s->pivot->hora_servicio ? substr($s->pivot->hora_servicio, 0, 5) : null,
            ])->all();

            $cotizacion = $this->cotizaciones->calcular(
                $habitacion->tipoHabitacion,
                $datos['fecha_llegada'],
                $datos['fecha_salida'],
                (int) $datos['adultos'] + (int) ($datos['ninos'] ?? 0),
                $pedidos
            );

            $antes = [
                'Llegada' => $reserva->fecha_llegada->format('d/m/Y'),
                'Salida' => $reserva->fecha_salida->format('d/m/Y'),
                'Habitación' => trim($reserva->tipoHabitacion?->nombre . ' ' . $reserva->habitacion?->numero),
                'Huéspedes' => $this->personas($reserva->adultos, $reserva->ninos),
                'Servicios' => $reserva->servicios->map(fn ($s) => $s->nombre . ' × ' . (int) $s->pivot->cantidad)->implode(', ') ?: 'Ninguno',
                'Hora de llegada' => $reserva->hora_estimada_llegada ? substr($reserva->hora_estimada_llegada, 0, 5) : '—',
                'Notas' => $reserva->notas ?: '—',
                'Total' => '$' . number_format((float) $reserva->total, 2),
            ];
            $despues = [
                'Llegada' => \Carbon\Carbon::parse($datos['fecha_llegada'])->format('d/m/Y'),
                'Salida' => \Carbon\Carbon::parse($datos['fecha_salida'])->format('d/m/Y'),
                'Habitación' => trim($habitacion->tipoHabitacion->nombre . ' ' . $habitacion->numero),
                'Huéspedes' => $this->personas($datos['adultos'], $datos['ninos'] ?? 0),
                'Servicios' => collect($cotizacion['servicios'])->map(fn ($l) => $l['nombre'] . ' × ' . $l['cantidad'])->implode(', ') ?: 'Ninguno',
                'Hora de llegada' => array_key_exists('hora_estimada_llegada', $datos)
                    ? ($datos['hora_estimada_llegada'] ?: '—') : $antes['Hora de llegada'],
                'Notas' => array_key_exists('notas', $datos) ? ($datos['notas'] ?: '—') : $antes['Notas'],
                'Total' => '$' . number_format($cotizacion['total'], 2),
            ];

            $cambios = [];
            foreach ($antes as $campo => $valor) {
                if ($valor !== $despues[$campo]) {
                    $cambios[] = ['campo' => $campo, 'antes' => $valor, 'despues' => $despues[$campo]];
                }
            }

            if (!$cambios) {
                return ['reserva' => $reserva, 'cambios' => []];
            }

            $reserva->update(array_intersect_key($datos, array_flip(['hora_estimada_llegada', 'notas'])) + [
                'habitacion_id' => $habitacion->id,
                'tipo_habitacion_id' => $habitacion->tipo_habitacion_id,
                'fecha_llegada' => $datos['fecha_llegada'],
                'fecha_salida' => $datos['fecha_salida'],
                'adultos' => $datos['adultos'],
                'ninos' => $datos['ninos'] ?? 0,
            ] + array_intersect_key($cotizacion, array_flip([
                'subtotal_alojamiento', 'subtotal_servicios', 'descuento', 'impuesto', 'total', 'anticipo_requerido',
            ])));

            $reserva->noches()->delete();
            $reserva->noches()->createMany(array_map(fn (array $noche) => [
                'habitacion_id' => $habitacion->id,
                'fecha' => $noche['fecha'],
                'temporada_id' => $noche['temporada_id'],
                'precio_noche' => $noche['precio_noche'],
            ], $cotizacion['noches']));

            $reserva->servicios()->detach();
            foreach ($cotizacion['servicios'] as $linea) {
                $reserva->servicios()->attach($linea['servicio_id'], [
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'subtotal' => $linea['subtotal'],
                    'fecha_servicio' => $linea['fecha_servicio'],
                    'hora_servicio' => $linea['hora_servicio'],
                    'estado_servicio' => Reserva::SERVICIO_CONTRATADO,
                ]);
            }

            //El estado no cambia: el historial se escribe a mano con el resumen de cambios
            $reserva->historial()->create([
                'estado_anterior' => $reserva->estado_reserva,
                'estado_nuevo' => $reserva->estado_reserva,
                'comentario' => mb_substr('Modificada: ' . collect($cambios)
                    ->map(fn ($c) => $c['campo'] === 'Notas' ? 'notas actualizadas' : "{$c['campo']} {$c['antes']} → {$c['despues']}")
                    ->implode('; ') . '.', 0, 255),
                'user_id' => auth()->id(),
            ]);

            return ['reserva' => $reserva->fresh(), 'cambios' => $cambios];
        }, self::INTENTOS);
    }

    private function personas($adultos, $ninos): string
    {
        return $adultos . ' ' . ((int) $adultos === 1 ? 'adulto' : 'adultos')
            . ((int) $ninos > 0 ? ' y ' . $ninos . ' ' . ((int) $ninos === 1 ? 'niño' : 'niños') : '');
    }
}
