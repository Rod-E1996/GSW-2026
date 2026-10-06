<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Habitacion;
use App\Models\TipoHabitacion;
use Carbon\Carbon;

/**
 * Reservas de ejemplo para tener datos tangibles en el panel y los reportes.
 * Los totales se calculan aqui de forma simple (precio base x noches + servicios);
 * la logica real de tarifas por temporada vive en el servicio de la Fase 2.
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
        $noches1 = $llegada1->diffInDays($salida1);
        $adultos1 = 2;

        $hospedaje1 = $noches1 * (float) $tipo1->precio_base;

        $reserva1 = Reserva::create([
            'codigo' => $this->codigo($anio, ++$consecutivo),
            'tipo_habitacion_id' => $tipo1->id,
            'fecha_llegada' => $llegada1->toDateString(),
            'fecha_salida' => $salida1->toDateString(),
            'adultos' => $adultos1,
            'ninos' => 0,
            'huesped_nombre' => 'Andrea Mejía',
            'huesped_documento' => '00000000-0',
            'huesped_email' => 'andrea.mejia@correo.com',
            'huesped_telefono' => '+503 7000-0001',
            'estado_reserva' => Reserva::CONFIRMADA,
            'total' => $hospedaje1,
        ]);

        $desayuno = Servicio::where('forma_cobro', Servicio::POR_PERSONA_NOCHE)->first();
        if ($desayuno) {
            $cantidad = $adultos1 * $noches1;
            $subtotal = $cantidad * (float) $desayuno->precio;
            $reserva1->servicios()->attach($desayuno->id, [
                'cantidad' => $cantidad,
                'precio_unitario' => $desayuno->precio,
                'subtotal' => $subtotal,
            ]);
            $reserva1->total = $hospedaje1 + $subtotal;
            $reserva1->save();
        }

        // --- Reserva 2: en casa, con habitacion asignada ---
        $tipo2 = $tipos->count() > 1 ? $tipos[1] : $tipos->first();
        $habitacion2 = Habitacion::where('estado', 1)->where('tipo_habitacion_id', $tipo2->id)->first();

        $llegada2 = Carbon::now()->subDays(2);
        $salida2 = (clone $llegada2)->addDays(3);
        $noches2 = $llegada2->diffInDays($salida2);

        Reserva::create([
            'codigo' => $this->codigo($anio, ++$consecutivo),
            'tipo_habitacion_id' => $tipo2->id,
            'habitacion_id' => $habitacion2->id ?? null,
            'fecha_llegada' => $llegada2->toDateString(),
            'fecha_salida' => $salida2->toDateString(),
            'adultos' => 3,
            'ninos' => 1,
            'huesped_nombre' => 'Familia Rivas',
            'huesped_documento' => '11111111-1',
            'huesped_email' => 'rivas@correo.com',
            'huesped_telefono' => '+503 7000-0002',
            'estado_reserva' => Reserva::EN_CASA,
            'total' => $noches2 * (float) $tipo2->precio_base,
        ]);
    }

    //Genera el codigo publico: HL-2026-0001
    private function codigo(int $anio, int $consecutivo): string
    {
        return 'HL-' . $anio . '-' . str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
    }
}
