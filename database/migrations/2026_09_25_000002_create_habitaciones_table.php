<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('habitaciones', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 10);
            $table->integer('piso');

            //Estado operativo de la habitacion: 1 disponible, 2 ocupada, 3 mantenimiento
            $table->integer('estado_habitacion')->default(1);

            $table->text('descripcion')->nullable();

            //Llaves foraneas
            $table->foreignId('tipo_habitacion_id')->constrained('tipos_habitacion');

            //Eliminado logico (1 activo, 0 eliminado)
            $table->integer('estado')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('habitaciones');
    }
};
