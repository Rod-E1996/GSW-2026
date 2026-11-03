<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Completa las temporadas segun el diccionario de datos 3.5.
     */
    public function up()
    {
        Schema::table('temporadas', function (Blueprint $table) {
            $table->string('nombre_en', 100)->nullable()->after('nombre');

            //Si dos temporadas se traslapan, manda la de mayor prioridad
            $table->integer('prioridad')->nullable()->default(0)->after('multiplicador');

            //Color para el calendario (#RRGGBB)
            $table->string('color', 7)->nullable()->after('prioridad');
        });
    }

    public function down()
    {
        Schema::table('temporadas', function (Blueprint $table) {
            $table->dropColumn(['nombre_en', 'prioridad', 'color']);
        });
    }
};
