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
        'habitacion_create',
        'habitacion_store',
        'habitacion_edit',
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
            ['103', 1, 'Doble',    Habitacion::OCUPADA,       'Vista al jardín.'],
            ['104', 1, 'Doble',    Habitacion::MANTENIMIENTO, 'Reparación de aire acondicionado.'],
            ['201', 2, 'Doble',    Habitacion::DISPONIBLE,    'Vista a la piscina.'],
            ['202', 2, 'Familiar', Habitacion::DISPONIBLE,    'Con balcón.'],
            ['203', 2, 'Familiar', Habitacion::DISPONIBLE,    null],
            ['301', 3, 'Suite',    Habitacion::DISPONIBLE,    'Vista al mar, último piso.'],
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
