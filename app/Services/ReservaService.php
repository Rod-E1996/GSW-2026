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
}
