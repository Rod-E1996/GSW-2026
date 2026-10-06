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
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();

            //Codigo publico de la reserva (ej. HL-2026-0427)
            $table->string('codigo', 20)->unique();

            //Tipo reservado; la habitacion concreta se asigna en el check-in
            $table->foreignId('tipo_habitacion_id')->constrained('tipos_habitacion');
            $table->foreignId('habitacion_id')->nullable()->constrained('habitaciones');

            $table->date('fecha_llegada');
            $table->date('fecha_salida');

            $table->integer('adultos')->default(1);
            $table->integer('ninos')->default(0);

            //Datos del huesped (la reserva web no requiere cuenta)
            $table->string('huesped_nombre', 150);
            $table->string('huesped_documento', 50)->nullable();
            $table->string('huesped_email', 150);
            $table->string('huesped_telefono', 30)->nullable();

            $table->time('hora_estimada_llegada')->nullable();

            //Estado de la reserva: 1 pendiente, 2 confirmada, 3 en casa, 4 salida, 5 cancelada
            $table->integer('estado_reserva')->default(1);

            //Total congelado al momento de reservar (hospedaje + servicios)
            $table->decimal('total', 10, 2)->default(0);

            //Quien creo la reserva (nullable: las reservas web no tienen usuario)
            $table->foreignId('user_id')->nullable()->constrained('users');

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
        Schema::dropIfExists('reservas');
    }
};
