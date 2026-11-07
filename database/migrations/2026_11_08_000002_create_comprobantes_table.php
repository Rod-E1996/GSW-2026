<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprobante digital de la reserva (diccionario de datos 3.16).
     */
    public function up()
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->string('numero', 30)->unique();
            $table->integer('version')->nullable();
            $table->char('qr_token', 36)->nullable()->unique();
            $table->string('ruta_pdf', 255)->nullable();
            $table->dateTime('emitido_at')->nullable();
            $table->dateTime('verificado_at')->nullable();
            $table->foreignId('verificado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('estado');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('comprobantes');
    }
};
