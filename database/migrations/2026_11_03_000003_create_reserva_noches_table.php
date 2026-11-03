<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalle de noches y precio por fecha (diccionario de datos 3.12).
     * Las reservas existentes con habitacion asignada reciben sus noches a partes
     * iguales, porque antes no se guardaba el precio de cada fecha.
     */
    public function up()
    {
        Schema::create('reserva_noches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('habitacion_id')->constrained('habitaciones');
            $table->date('fecha');
            $table->foreignId('temporada_id')->nullable()->constrained('temporadas')->nullOnDelete();
            $table->decimal('precio_noche', 8, 2);
            $table->timestamps();

            $table->unique(['reserva_id', 'fecha']);
            //La consulta de disponibilidad busca noches por habitacion y fecha
            $table->index(['habitacion_id', 'fecha']);
        });

        foreach (DB::table('reservas')->whereNotNull('habitacion_id')->orderBy('id')->get() as $reserva) {
            $fecha = Carbon::parse($reserva->fecha_llegada);
            $noches = (int) $fecha->diffInDays(Carbon::parse($reserva->fecha_salida));
            if ($noches < 1) {
                continue;
            }

            $restante = (float) $reserva->subtotal_alojamiento;

            for ($i = $noches; $i > 0; $i--) {
                //La ultima noche absorbe los centavos del redondeo
                $precio = $i === 1 ? $restante : round($restante / $i, 2);
                $restante = round($restante - $precio, 2);

                DB::table('reserva_noches')->insert([
                    'reserva_id' => $reserva->id,
                    'habitacion_id' => $reserva->habitacion_id,
                    'fecha' => $fecha->toDateString(),
                    'precio_noche' => $precio,
                    'created_at' => $reserva->created_at,
                    'updated_at' => $reserva->created_at,
                ]);

                $fecha->addDay();
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('reserva_noches');
    }
};
