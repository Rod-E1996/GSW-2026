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
        Schema::create('temporadas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 100);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');

            //Multiplicador sobre el precio base (ej. 1.00, 1.15, 1.45)
            $table->decimal('multiplicador', 5, 2)->default(1.00);

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
        Schema::dropIfExists('temporadas');
    }
};
