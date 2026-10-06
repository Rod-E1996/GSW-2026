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
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();

            //Icono de Bootstrap Icons (ej. bi-cup-hot)
            $table->string('icono', 50)->nullable();

            $table->decimal('precio', 8, 2);

            //Forma de cobro: por_persona_noche, por_persona, por_noche, fijo
            $table->string('forma_cobro', 30)->default('fijo');

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
        Schema::dropIfExists('servicios');
    }
};
