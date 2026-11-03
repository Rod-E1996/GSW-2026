<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrupacion de los servicios complementarios (diccionario 3.8).
     */
    public function up()
    {
        Schema::create('categorias_servicio', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 80);
            $table->string('nombre_en', 80)->nullable();
            $table->string('icono', 50)->nullable();
            $table->integer('orden')->nullable()->default(0);

            //Eliminado logico (1 activo, 0 eliminado)
            $table->integer('estado')->default(1);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('categorias_servicio');
    }
};
