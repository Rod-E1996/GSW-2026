<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles del hotel y usuarios de prueba.
 *
 * Es seguro ejecutarlo sobre una base de datos ya sembrada:
 *   php artisan db:seed --class=RolesHotelSeeder
 *
 * Roles del sistema (documento, objetivo específico 5):
 *   - Super Administrador : todos los permisos, administra el hotel (lo crea PermissionSeeder)
 *   - Recepcionista       : operación diaria (reservas, check-in/out, consulta de catálogos)
 *   - Huésped             : perfil y, más adelante, sus propias reservaciones
 *
 * Además elimina los roles "Invitado" y "Administrador" de la plantilla base:
 * los usuarios de Invitado pasan a Huésped y los de Administrador a Super Administrador.
 */
class RolesHotelSeeder extends Seeder
{
    //Permisos de cada rol. Cuando se agregue un módulo nuevo, sumar aquí sus permisos.
    public static $permisosRecepcionista = [
        'dashboard',

        'perfil_show',
        'perfil_edit',
        'perfil_edit_password',
        'perfil_cerrar_session',

        'tipo_habitacion_index',
        'tipo_habitacion_show',

        'habitacion_index',
        'habitacion_show',
        'habitacion_estado',
    ];

    public static $permisosHuesped = [
        'perfil_show',
        'perfil_edit',
        'perfil_edit_password',
        'perfil_cerrar_session',
    ];

    //Usuarios de prueba, uno por rol (contraseña: 12345678)
    public static $usuariosPrueba = [
        ['name' => 'Recepción Hotel',     'email' => 'recepcion@hotellink.com',     'rol' => 'Recepcionista'],
        ['name' => 'Huésped de Prueba',   'email' => 'huesped@hotellink.com',       'rol' => 'Huésped'],
    ];

    public function run()
    {
        $recepcionista = Role::firstOrCreate(['name' => 'Recepcionista', 'guard_name' => 'web']);
        $huesped = Role::firstOrCreate(['name' => 'Huésped', 'guard_name' => 'web']);

        //Solo se asignan permisos que ya existan, por si el seeder corre antes que un módulo nuevo
        $recepcionista->syncPermissions($this->permisosExistentes(self::$permisosRecepcionista));
        $huesped->syncPermissions($this->permisosExistentes(self::$permisosHuesped));

        //Eliminar los roles de la plantilla base que el hotel no usa, moviendo a sus usuarios
        $this->reemplazarRol('Invitado', $huesped);
        $this->reemplazarRol('Administrador', Role::where('name', 'Super Administrador')->first());

        //Usuarios de prueba
        foreach (self::$usuariosPrueba as $datos) {
            $user = User::firstOrCreate(
                ['email' => $datos['email']],
                [
                    'name' => $datos['name'],
                    'password' => Hash::make('12345678'),
                ]
            );

            if (!$user->hasRole($datos['rol'])) {
                $user->assignRole($datos['rol']);
            }
        }

        //Limpiar cache de permisos de spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function reemplazarRol(string $nombreViejo, ?Role $rolNuevo)
    {
        $viejo = Role::where('name', $nombreViejo)->first();
        if (!$viejo) {
            return;
        }

        foreach ($viejo->users as $user) {
            $user->removeRole($viejo);
            if ($rolNuevo && !$user->hasRole($rolNuevo)) {
                $user->assignRole($rolNuevo);
            }
        }

        $viejo->delete();
    }

    private function permisosExistentes(array $nombres)
    {
        return Permission::whereIn('name', $nombres)->pluck('name')->toArray();
    }
}
