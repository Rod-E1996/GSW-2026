<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoHabitacion;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeder del módulo de tipos de habitación.
 *
 * Es seguro ejecutarlo sobre una base de datos ya sembrada:
 *   php artisan db:seed --class=TiposHabitacionSeeder
 *
 * - Crea los permisos del módulo si aún no existen y se los asigna
 *   al rol Super Administrador.
 * - Inserta los tipos de habitación de ejemplo si la tabla está vacía.
 */
class TiposHabitacionSeeder extends Seeder
{
    public static $permisos = [
        'tipo_habitacion_index',
        'tipo_habitacion_show',
        'tipo_habitacion_create',
        'tipo_habitacion_store',
        'tipo_habitacion_edit',
        'tipo_habitacion_update',
        'tipo_habitacion_destroy',
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

        //Limpiar cache de permisos de spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        //Datos de ejemplo
        if (TipoHabitacion::count() > 0) {
            return;
        }

        $tipos = [
            [
                'nombre' => 'Sencilla',
                'capacidad' => 1,
                'precio_base' => 35.00,
                'descripcion' => 'Una cama individual, baño privado, aire acondicionado y televisión por cable.',
            ],
            [
                'nombre' => 'Doble',
                'capacidad' => 2,
                'precio_base' => 55.00,
                'descripcion' => 'Una cama matrimonial o dos camas individuales, baño privado, aire acondicionado y wifi.',
            ],
            [
                'nombre' => 'Familiar',
                'capacidad' => 4,
                'precio_base' => 85.00,
                'descripcion' => 'Una cama matrimonial y dos individuales, baño privado, minibar y balcón.',
            ],
            [
                'nombre' => 'Suite',
                'capacidad' => 2,
                'precio_base' => 120.00,
                'descripcion' => 'Cama king, sala de estar, jacuzzi, minibar y vista al mar.',
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoHabitacion::create($tipo);
        }
    }
}
