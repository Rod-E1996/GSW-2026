<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    //Categoria inicial de los servicios que ya existen, segun su nombre
    private const CATEGORIAS = [
        'Alimentación' => 'Alimentación y bebidas',
        'Lavandería' => 'Servicios al huésped',
        'Transporte' => 'Servicios al huésped',
        'Salón de eventos' => 'Eventos',
        'Actividades recreativas' => 'Recreación',
    ];

    /**
     * Completa el catalogo segun el diccionario 3.9. La categoria es obligatoria,
     * asi que primero se asigna una a cada servicio ya registrado.
     */
    public function up()
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_servicio_id')->nullable()->after('id');
            $table->string('nombre_en', 100)->nullable()->after('nombre');
            $table->text('descripcion_en')->nullable()->after('descripcion');
            $table->string('imagen', 255)->nullable()->after('icono');
            $table->boolean('requiere_fecha')->default(false)->after('forma_cobro');
        });

        foreach (DB::table('servicios')->orderBy('id')->get(['id', 'nombre']) as $servicio) {
            DB::table('servicios')->where('id', $servicio->id)->update([
                'categoria_servicio_id' => $this->categoria(self::CATEGORIAS[$servicio->nombre] ?? 'Otros servicios'),
            ]);
        }

        Schema::table('servicios', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_servicio_id')->nullable(false)->change();
            $table->foreign('categoria_servicio_id')->references('id')->on('categorias_servicio');
        });
    }

    private function categoria(string $nombre): int
    {
        $id = DB::table('categorias_servicio')->where('nombre', $nombre)->value('id');

        return $id ?? DB::table('categorias_servicio')->insertGetId([
            'nombre' => $nombre,
            'orden' => DB::table('categorias_servicio')->count() + 1,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropForeign(['categoria_servicio_id']);
        });

        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn(['categoria_servicio_id', 'nombre_en', 'descripcion_en', 'imagen', 'requiere_fecha']);
        });
    }
};
