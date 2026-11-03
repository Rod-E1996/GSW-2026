<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Completa los servicios contratados segun el diccionario de datos 3.13.
     */
    public function up()
    {
        Schema::table('reserva_servicio', function (Blueprint $table) {
            $table->date('fecha_servicio')->nullable()->after('subtotal');
            $table->time('hora_servicio')->nullable()->after('fecha_servicio');
            $table->integer('estado_servicio')->nullable()->default(1)->after('hora_servicio');
            $table->text('notas')->nullable()->after('estado_servicio');
        });
    }

    public function down()
    {
        Schema::table('reserva_servicio', function (Blueprint $table) {
            $table->dropColumn(['fecha_servicio', 'hora_servicio', 'estado_servicio', 'notas']);
        });
    }
};
