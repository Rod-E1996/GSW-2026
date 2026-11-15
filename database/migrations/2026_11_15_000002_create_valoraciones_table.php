<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

//Valoracion de una estadia finalizada: el huesped la escribe, el hotel la modera y responde
return new class extends Migration
{
    public function up()
    {
        Schema::create('valoraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete(); //una por estadia
            $table->foreignId('huesped_id')->constrained('huespedes');
            $table->unsignedTinyInteger('puntuacion');                 //1 a 5
            $table->text('comentario')->nullable();
            $table->string('estado', 20)->default('pendiente');        //pendiente | aprobada | rechazada
            $table->text('respuesta')->nullable();                     //respuesta del hotel
            $table->timestamp('moderada_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); //quien la modero
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('valoraciones');
    }
};
