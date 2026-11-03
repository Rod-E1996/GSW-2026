<?php

namespace App\Services;

use App\Exceptions\HabitacionNoDisponible;
use App\Models\Configuracion;
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
        private PrecioService $precios
    ) {
    }

    /**
     * $datos: habitacion_id, huesped_id, fecha_llegada, fecha_salida, adultos y,
     * opcionales, ninos, hora_estimada_llegada, canal, notas, user_id.
     *
     * @throws HabitacionNoDisponible si otra reserva o un bloqueo ocupo la habitacion
     */
    public function crear(array $datos): Reserva
    {
        $tipoId = Habitacion::whereKey($datos['habitacion_id'])->value('tipo_habitacion_id');

        return DB::transaction(function () use ($datos, $tipoId) {
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

            $cotizacion = $this->precios->cotizar($habitacion->tipoHabitacion, $datos['fecha_llegada'], $datos['fecha_salida']);

            $reserva = Reserva::create($datos + $this->importes($cotizacion['subtotal']) + [
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

            return $reserva;
        }, self::INTENTOS);
    }

    private function importes(float $alojamiento): array
    {
        $impuesto = round($alojamiento * Configuracion::valor('impuesto_porcentaje') / 100, 2);
        $total = round($alojamiento + $impuesto, 2);

        return [
            'subtotal_alojamiento' => $alojamiento,
            'subtotal_servicios' => 0,
            'descuento' => 0,
            'impuesto' => $impuesto,
            'total' => $total,
            'anticipo_requerido' => round($total * Configuracion::valor('anticipo_porcentaje') / 100, 2),
        ];
    }
}
