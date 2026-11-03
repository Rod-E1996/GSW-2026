<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parametros generales del sistema (diccionario de datos 3.21).
     */
    public function up()
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->text('valor')->nullable();
            $table->string('tipo', 20)->nullable();
            $table->string('grupo', 40)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('configuraciones');
    }
};
