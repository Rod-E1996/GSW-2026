<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correos y avisos enviados (diccionario de datos 3.19).
     */
    public function up()
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 40)->nullable();
            $table->string('canal', 20)->nullable();
            $table->string('destinatario', 200)->nullable();
            $table->string('asunto', 200)->nullable();
            $table->integer('estado_envio');
            $table->integer('intentos')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('enviado_at')->nullable();
            $table->timestamps();

            $table->index(['estado_envio', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
};
