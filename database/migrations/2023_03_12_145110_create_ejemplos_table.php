<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEjemplosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ejemplos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 200);
            $table->string('telefono', 9);
            $table->date('fecha');
            $table->double('cantidad', 8, 2);
            $table->integer('porcentaje');
            $table->integer('select_quemado');

            //Llaves foraneas
            $table->foreignIdFor(\App\Models\AuditarAccion::class)->references('id')->on('auditar_acciones');

            $table->integer('estado')->default(1);
            $table->text('descripcion')->nullable();

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
        Schema::dropIfExists('ejemplos');
    }
}
