<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permisos de los modulos de reservas, catalogos, gestion y configuracion.
 * Es seguro re-ejecutarlo: no duplica permisos ni quita los ya asignados.
 */
class PermisosHotelSeeder extends Seeder
{
    public static $permisos = [
        //Reservas y operacion de recepcion
        'reserva_index',
        'reserva_show',
        'reserva_store',
        'reserva_update',
        'reserva_confirmar',
        'reserva_cancelar',
        'reserva_checkin',
        'reserva_checkout',

        //Huespedes
        'huesped_index',
        'huesped_show',
        'huesped_store',
        'huesped_update',

        //Servicios complementarios
        'categoria_servicio_index',
        'categoria_servicio_store',
        'categoria_servicio_update',
        'categoria_servicio_destroy',
        'servicio_index',
        'servicio_store',
        'servicio_update',
        'servicio_destroy',

        //Temporadas, tarifas y bloqueos
        'temporada_index',
        'temporada_store',
        'temporada_update',
        'temporada_destroy',
        'tarifa_index',
        'tarifa_update',
        'bloqueo_index',
        'bloqueo_store',
        'bloqueo_update',
        'bloqueo_destroy',

        //Gestion
        'valoracion_index',
        'valoracion_moderar',
        'reporte_index',
        'reporte_exportar',
        'respaldo_index',
        'respaldo_store',

        //Parametros del sistema
        'configuracion_index',
        'configuracion_update',
    ];

    public function run()
    {
        foreach (self::$permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        //Con una instalacion nueva los roles aun no existen: los crean PermissionSeeder y RolesHotelSeeder
        Role::where('name', 'Super Administrador')->first()?->givePermissionTo(self::$permisos);
        Role::where('name', 'Recepcionista')->first()?->givePermissionTo(
            array_values(array_intersect(self::$permisos, RolesHotelSeeder::$permisosRecepcionista))
        );

        //Limpiar cache de permisos de spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
