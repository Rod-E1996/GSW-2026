<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Huespedes del hotel (diccionario de datos 3.10). No todos tienen cuenta:
     * las reservas telefonicas o presenciales pueden no tener usuario asociado.
     */
    public function up()
    {
        Schema::create('huespedes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('tipo_documento', 20)->nullable();
            $table->string('documento', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('nacionalidad', 80)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('direccion', 255)->nullable();
            $table->text('notas')->nullable();
            $table->integer('estado')->default(1);
            $table->timestamps();

            //Recepcion busca al huesped por documento o correo
            $table->index('documento');
            $table->index('email');
        });
    }

    public function down()
    {
        Schema::dropIfExists('huespedes');
    }
};
