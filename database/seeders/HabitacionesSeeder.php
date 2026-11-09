<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Habitacion;
use App\Models\TipoHabitacion;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeder del módulo de habitaciones.
 *
 * Es seguro ejecutarlo sobre una base de datos ya sembrada:
 *   php artisan db:seed --class=HabitacionesSeeder
 *
 * - Crea los permisos del módulo si aún no existen y se los asigna al Super Administrador.
 * - Recepcionista recibe solo ver y cambiar estado (se sincroniza en RolesHotelSeeder).
 * - Inserta habitaciones de ejemplo si la tabla está vacía (requiere TiposHabitacionSeeder).
 */
class HabitacionesSeeder extends Seeder
{
    public static $permisos = [
        'habitacion_index',
        'habitacion_show',
        'habitacion_store',
        'habitacion_update',
        'habitacion_estado',
        'habitacion_destroy',
    ];

    public function run()
    {
        //Permisos del módulo (firstOrCreate para no duplicar)
        foreach (self::$permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'Super Administrador')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(self::$permisos);
        }

        $recepcionista = Role::where('name', 'Recepcionista')->first();
        if ($recepcionista) {
            $recepcionista->givePermissionTo(['habitacion_index', 'habitacion_show', 'habitacion_estado']);
        }

        //Limpiar cache de permisos de spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        //Datos de ejemplo
        if (Habitacion::count() > 0) {
            return;
        }

        $tipos = TipoHabitacion::where('estado', 1)->get()->keyBy('nombre');
        if ($tipos->isEmpty()) {
            $this->command->warn('No hay tipos de habitación. Ejecute primero TiposHabitacionSeeder.');
            return;
        }

        //[numero, piso, tipo, estado_habitacion, descripcion]
        $habitaciones = [
            ['101', 1, 'Sencilla', Habitacion::DISPONIBLE,    'Junto a recepción.'],
            ['102', 1, 'Sencilla', Habitacion::DISPONIBLE,    null],
            ['103', 1, 'Doble',    Habitacion::LIMPIEZA,      'Vista al jardín.'],
            ['104', 1, 'Doble',    Habitacion::MANTENIMIENTO, 'Reparación de aire acondicionado.'],
            ['105', 1, 'Sencilla', Habitacion::DISPONIBLE,    'Accesible para silla de ruedas.'],
            ['106', 1, 'Doble',    Habitacion::DISPONIBLE,    'Acceso directo al jardín.'],
            ['201', 2, 'Doble',    Habitacion::DISPONIBLE,    'Vista a la piscina.'],
            ['202', 2, 'Familiar', Habitacion::DISPONIBLE,    'Con balcón.'],
            ['203', 2, 'Familiar', Habitacion::DISPONIBLE,    null],
            ['204', 2, 'Doble',    Habitacion::DISPONIBLE,    'Vista a la piscina.'],
            ['205', 2, 'Doble',    Habitacion::DISPONIBLE,    null],
            ['206', 2, 'Familiar', Habitacion::DISPONIBLE,    'Conecta con la 205.'],
            ['301', 3, 'Suite',    Habitacion::DISPONIBLE,    'Vista al mar, último piso.'],
            ['302', 3, 'Suite',    Habitacion::DISPONIBLE,    'Terraza privada con hamacas.'],
            ['303', 3, 'Familiar', Habitacion::DISPONIBLE,    'Vista parcial al mar.'],
            ['304', 3, 'Doble',    Habitacion::DISPONIBLE,    'Vista al mar.'],
            ['305', 3, 'Doble',    Habitacion::DISPONIBLE,    null],
            ['401', 4, 'Suite',    Habitacion::DISPONIBLE,    'Suite principal: vista panorámica al océano.'],
            ['402', 4, 'Suite',    Habitacion::DISPONIBLE,    'Jacuzzi exterior frente al mar.'],
        ];

        foreach ($habitaciones as [$numero, $piso, $tipo, $estadoHabitacion, $descripcion]) {
            $tipoHabitacion = $tipos->get($tipo) ?? $tipos->first();

            Habitacion::create([
                'numero' => $numero,
                'piso' => $piso,
                'tipo_habitacion_id' => $tipoHabitacion->id,
                'estado_habitacion' => $estadoHabitacion,
                'descripcion' => $descripcion,
            ]);
        }
    }
}
