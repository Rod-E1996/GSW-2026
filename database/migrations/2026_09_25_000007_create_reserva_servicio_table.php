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
        Schema::create('reserva_servicio', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios');

            $table->integer('cantidad')->default(1);

            //Precio congelado al momento de reservar (no cambia aunque luego suba el catalogo)
            $table->decimal('precio_unitario', 8, 2);
            $table->decimal('subtotal', 10, 2);

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
        Schema::dropIfExists('reserva_servicio');
    }
};
