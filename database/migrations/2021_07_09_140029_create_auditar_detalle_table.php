<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditarDetalleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auditar_detalle', function (Blueprint $table) {
            $table->id();
            $table->string( 'name', 200 )->nullable();
            $table->string( 'email', 200 )->nullable();
            $table->text( 'antes' )->nullable();
            $table->text( 'despues' )->nullable();
            $table->integer( 'primary_key' )->nullable();
            $table->string( 'nombre_modificado', 200 )->nullable();

            //Llaves foraneas
            $table->foreignIdFor(\App\Models\AuditarAccion::class)->references('id')->on('auditar_acciones');
            $table->foreignIdFor(\App\Models\AuditarTabla::class)->references('id')->on('auditar_tablas');
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
        Schema::dropIfExists('auditar_detalle');
    }
}
