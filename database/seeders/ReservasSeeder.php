<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Configuracion;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Habitacion;
use App\Models\TipoHabitacion;
use Carbon\Carbon;

/**
 * Reservas de ejemplo para tener datos tangibles en el panel y los reportes.
 * Los totales se calculan aqui de forma simple (precio base x noches + servicios);
 * las reservas reales pasan por ReservaService, que aplica temporadas y tarifas.
 * Es seguro re-ejecutarlo: solo inserta si la tabla esta vacia.
 */
class ReservasSeeder extends Seeder
{
    public function run()
    {
        if (Reserva::count() > 0) {
            return;
        }

        $tipos = TipoHabitacion::where('estado', 1)->orderBy('precio_base')->get();
        if ($tipos->isEmpty()) {
            return;
        }

        $anio = Carbon::now()->year;
        $consecutivo = 0;

        // --- Reserva 1: confirmada, con servicio de desayuno ---
        $tipo1 = $tipos->first();
        $llegada1 = Carbon::now()->addDays(10);
        $salida1 = (clone $llegada1)->addDays(3);
        $noches1 = (int) $llegada1->diffInDays($salida1);
        $adultos1 = 2;

        $desayuno = Servicio::where('forma_cobro', Servicio::POR_PERSONA_NOCHE)->first();
        $cantidad = $adultos1 * $noches1;
        $servicios1 = $desayuno ? $cantidad * (float) $desayuno->precio : 0;

        $reserva1 = Reserva::create(array_merge([
            'codigo' => $this->codigo($anio, ++$consecutivo),
            'huesped_id' => Huesped::create([
                'nombres' => 'Andrea',
                'apellidos' => 'Mejía',
                'tipo_documento' => 'DUI',
                'documento' => '00000000-0',
                'email' => 'andrea.mejia@correo.com',
                'telefono' => '+503 7000-0001',
            ])->id,
            'tipo_habitacion_id' => $tipo1->id,
            'fecha_llegada' => $llegada1->toDateString(),
            'fecha_salida' => $salida1->toDateString(),
            'adultos' => $adultos1,
            'ninos' => 0,
            'canal' => Reserva::CANAL_WEB,
            'estado_reserva' => Reserva::CONFIRMADA,
        ], $this->importes($noches1 * (float) $tipo1->precio_base, $servicios1)));

        if ($desayuno) {
            $reserva1->servicios()->attach($desayuno->id, [
                'cantidad' => $cantidad,
                'precio_unitario' => $desayuno->precio,
                'subtotal' => $servicios1,
            ]);
        }

        // --- Reserva 2: en estadia, con habitacion asignada ---
        $tipo2 = $tipos->count() > 1 ? $tipos[1] : $tipos->first();
        $habitacion2 = Habitacion::where('estado', 1)->where('tipo_habitacion_id', $tipo2->id)->first();

        $llegada2 = Carbon::now()->subDays(2);
        $salida2 = (clone $llegada2)->addDays(3);
        $noches2 = (int) $llegada2->diffInDays($salida2);

        $reserva2 = Reserva::create(array_merge([
            'codigo' => $this->codigo($anio, ++$consecutivo),
            'huesped_id' => Huesped::create([
                'nombres' => 'Familia',
                'apellidos' => 'Rivas',
                'tipo_documento' => 'DUI',
                'documento' => '11111111-1',
                'email' => 'rivas@correo.com',
                'telefono' => '+503 7000-0002',
            ])->id,
            'tipo_habitacion_id' => $tipo2->id,
            'habitacion_id' => $habitacion2->id ?? null,
            'fecha_llegada' => $llegada2->toDateString(),
            'fecha_salida' => $salida2->toDateString(),
            'adultos' => 3,
            'ninos' => 1,
            'canal' => Reserva::CANAL_PRESENCIAL,
            'estado_reserva' => Reserva::EN_ESTADIA,
            'check_in_at' => $llegada2->toDateTimeString(),
        ], $this->importes($noches2 * (float) $tipo2->precio_base, 0)));

        if ($habitacion2) {
            $fecha = $llegada2->copy();
            for ($i = 0; $i < $noches2; $i++) {
                $reserva2->noches()->create([
                    'habitacion_id' => $habitacion2->id,
                    'fecha' => $fecha->toDateString(),
                    'precio_noche' => $tipo2->precio_base,
                ]);
                $fecha->addDay();
            }
        }
    }

    //Desglose de importes segun los parametros del sistema
    private function importes(float $alojamiento, float $servicios): array
    {
        $impuesto = round(($alojamiento + $servicios) * Configuracion::valor('impuesto_porcentaje', 0) / 100, 2);
        $total = $alojamiento + $servicios + $impuesto;

        return [
            'subtotal_alojamiento' => $alojamiento,
            'subtotal_servicios' => $servicios,
            'impuesto' => $impuesto,
            'total' => $total,
            'anticipo_requerido' => round($total * Configuracion::valor('anticipo_porcentaje', 0) / 100, 2),
        ];
    }

    //Genera el codigo publico: HL-2026-0001
    private function codigo(int $anio, int $consecutivo): string
    {
        return 'HL-' . $anio . '-' . str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
    }
}
