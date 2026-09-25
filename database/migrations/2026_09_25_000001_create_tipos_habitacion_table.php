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
        Schema::create('tipos_habitacion', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 100);
            $table->integer('capacidad');
            $table->decimal('precio_base', 8, 2);
            $table->text('descripcion')->nullable();

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
        Schema::dropIfExists('tipos_habitacion');
    }
};
