<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inhabilitacion temporal de habitaciones (diccionario 3.7).
     * fecha_fin es el ultimo dia bloqueado, inclusive.
     */
    public function up()
    {
        Schema::create('bloqueos_habitacion', function (Blueprint $table) {
            $table->id();

            //Llaves foraneas
            $table->foreignId('habitacion_id')->constrained('habitaciones');

            $table->date('fecha_inicio');
            $table->date('fecha_fin');

            //Motivo: mantenimiento, uso_interno
            $table->string('motivo', 30)->nullable();
            $table->text('descripcion')->nullable();

            //Usuario que registro el bloqueo
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            //Eliminado logico (1 activo, 0 eliminado)
            $table->integer('estado')->default(1);

            $table->timestamps();

            $table->index(['habitacion_id', 'fecha_inicio', 'fecha_fin']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bloqueos_habitacion');
    }
};
