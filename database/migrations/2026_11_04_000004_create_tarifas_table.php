<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Precio por noche de cada tipo de habitacion en cada temporada (diccionario 3.6).
     */
    public function up()
    {
        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();

            //Llaves foraneas
            $table->foreignId('tipo_habitacion_id')->constrained('tipos_habitacion');
            $table->foreignId('temporada_id')->constrained('temporadas');

            $table->decimal('precio_noche', 8, 2);

            //Eliminado logico (1 activo, 0 eliminado)
            $table->integer('estado')->default(1);

            $table->timestamps();

            $table->unique(['tipo_habitacion_id', 'temporada_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tarifas');
    }
};
