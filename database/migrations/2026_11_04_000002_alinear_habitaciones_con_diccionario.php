<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Segun el diccionario 3.3 el numero es unico y el estado es fisico
     * (disponible, limpieza, mantenimiento). "Ocupada" desaparece: la ocupacion se
     * deduce de las reservas, asi que esas habitaciones pasan a disponible.
     */
    public function up()
    {
        $this->liberarNumerosRepetidos();

        Schema::table('habitaciones', function (Blueprint $table) {
            $table->string('estado_habitacion', 20)->nullable()->default('disponible')->change();
        });

        DB::table('habitaciones')->whereIn('estado_habitacion', ['1', '2'])->update(['estado_habitacion' => 'disponible']);
        DB::table('habitaciones')->where('estado_habitacion', '3')->update(['estado_habitacion' => 'mantenimiento']);

        Schema::table('habitaciones', function (Blueprint $table) {
            $table->unique('numero');
        });
    }

    //Las eliminadas ceden su numero a la activa. Dos activas repetidas las debe resolver el hotel.
    private function liberarNumerosRepetidos(): void
    {
        $repetidos = DB::table('habitaciones')
            ->select('numero')
            ->groupBy('numero')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('numero');

        foreach ($repetidos as $numero) {
            $filas = DB::table('habitaciones')->where('numero', $numero)->orderByDesc('estado')->orderByDesc('id')->get();

            if ($filas->where('estado', 1)->count() > 1) {
                throw new RuntimeException("Hay varias habitaciones activas con el numero $numero. Corrijalo antes de migrar.");
            }

            foreach ($filas->slice(1) as $fila) {
                DB::table('habitaciones')->where('id', $fila->id)->update(['numero' => 'ELIM-' . $fila->id]);
            }
        }
    }

    //Al revertir no se puede saber cuales estaban ocupadas: vuelven como disponibles.
    public function down()
    {
        Schema::table('habitaciones', function (Blueprint $table) {
            $table->dropUnique(['numero']);
        });

        DB::table('habitaciones')->where('estado_habitacion', 'mantenimiento')->update(['estado_habitacion' => '3']);
        DB::table('habitaciones')->where('estado_habitacion', '!=', '3')->update(['estado_habitacion' => '1']);

        Schema::table('habitaciones', function (Blueprint $table) {
            $table->integer('estado_habitacion')->nullable(false)->default(1)->change();
        });
    }
};
