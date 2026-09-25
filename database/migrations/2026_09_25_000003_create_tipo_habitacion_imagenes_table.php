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
        Schema::create('tipo_habitacion_imagenes', function (Blueprint $table) {
            $table->id();

            //Ruta relativa dentro del disco "public" (storage/app/public)
            $table->string('ruta', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('principal')->default(false);

            //Llaves foraneas
            $table->foreignId('tipo_habitacion_id')->constrained('tipos_habitacion')->cascadeOnDelete();

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
        Schema::dropIfExists('tipo_habitacion_imagenes');
    }
};
