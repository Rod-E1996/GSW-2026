<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQueueControlTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('queue_control', function (Blueprint $table) {
            $table->id();
            $table->text('titulo');
            $table->integer('total_procesos');
            $table->integer('progreso');
            $table->integer('pendiente')->default(1);
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->integer('estado')->default(1);

            //Llaves foraneas
            $table->foreignIdFor(\App\Models\User::class)->nullable()->references('id')->on('users');

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
        Schema::dropIfExists('queue_control');
    }
}
