<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anticipos y pagos de la reserva (diccionario de datos 3.15).
     * referencia_externa es unica: una misma respuesta de la pasarela no registra dos pagos.
     */
    public function up()
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->string('tipo_pago', 20)->nullable();
            $table->string('metodo_pago', 30)->nullable();
            $table->string('pasarela', 30)->nullable();
            $table->string('referencia_externa', 100)->nullable()->unique();
            $table->decimal('monto', 10, 2);
            $table->integer('estado_pago');
            $table->json('respuesta_pasarela')->nullable();
            $table->dateTime('pagado_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reserva_id', 'estado_pago']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pagos');
    }
};
