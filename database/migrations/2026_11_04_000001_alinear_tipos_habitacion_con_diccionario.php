<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Completa los tipos de habitacion segun el diccionario de datos 3.2.
     * El slug se calcula tambien para los eliminados porque es unico en toda la tabla.
     */
    public function up()
    {
        Schema::table('tipos_habitacion', function (Blueprint $table) {
            $table->string('nombre_en', 100)->nullable()->after('nombre');
            $table->string('slug', 120)->nullable()->after('nombre_en');
            $table->text('descripcion_en')->nullable()->after('descripcion');
            $table->integer('orden')->nullable()->default(0)->after('descripcion_en');
        });

        $usados = [];
        foreach (DB::table('tipos_habitacion')->orderBy('id')->get(['id', 'nombre']) as $tipo) {
            $base = Str::limit(Str::slug($tipo->nombre) ?: 'tipo-' . $tipo->id, 110, '');
            $slug = $base;
            for ($i = 2; in_array($slug, $usados); $i++) {
                $slug = $base . '-' . $i;
            }
            $usados[] = $slug;
            DB::table('tipos_habitacion')->where('id', $tipo->id)->update(['slug' => $slug]);
        }

        Schema::table('tipos_habitacion', function (Blueprint $table) {
            $table->string('slug', 120)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down()
    {
        Schema::table('tipos_habitacion', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        Schema::table('tipos_habitacion', function (Blueprint $table) {
            $table->dropColumn(['nombre_en', 'slug', 'descripcion_en', 'orden']);
        });
    }
};
