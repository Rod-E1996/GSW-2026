<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitacora de cambios de estado de la reserva (diccionario de datos 3.14).
     */
    public function up()
    {
        Schema::create('reserva_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->integer('estado_anterior')->nullable();
            $table->integer('estado_nuevo');
            $table->string('comentario', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        foreach (DB::table('reservas')->orderBy('id')->get() as $reserva) {
            DB::table('reserva_historial')->insert([
                'reserva_id' => $reserva->id,
                'estado_nuevo' => $reserva->estado_reserva,
                'comentario' => 'Estado que tenía la reserva al crear el historial.',
                'user_id' => $reserva->user_id,
                'created_at' => $reserva->updated_at,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('reserva_historial');
    }
};
