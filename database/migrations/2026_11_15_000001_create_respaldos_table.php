<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

//Respaldos de la base generados desde el panel o por la tarea programada diaria
return new class extends Migration
{
    public function up()
    {
        Schema::create('respaldos', function (Blueprint $table) {
            $table->id();
            $table->string('archivo', 150);                  //nombre del archivo en storage/app/respaldos
            $table->unsignedBigInteger('tamano')->default(0); //bytes
            $table->string('origen', 20)->default('manual'); //manual | programado
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('respaldos');
    }
};
