<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //Insertar el usuario administrador por defecto
        $user = User::create([
            'name' => 'Administrador',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('12345678'),
        ]);

        //Lista de permisos

        //Permisos
        Permission::create(['name' => 'permiso_index']);
        Permission::create(['name' => 'permiso_move']);

        //Roles
        Permission::create(['name' => 'role_index']);
        Permission::create(['name' => 'role_show']);
        Permission::create(['name' => 'role_create']);
        Permission::create(['name' => 'role_store']);
        Permission::create(['name' => 'role_edit']);
        Permission::create(['name' => 'role_update']);
        Permission::create(['name' => 'role_destroy']);
        Permission::create(['name' => 'role_move_permiso']);

        //Usuarios
        Permission::create(['name' => 'usuario_index']);
        Permission::create(['name' => 'usuario_show']);
        Permission::create(['name' => 'usuario_create']);
        Permission::create(['name' => 'usuario_store']);
        Permission::create(['name' => 'usuario_edit']);
        Permission::create(['name' => 'usuario_update']);
        Permission::create(['name' => 'usuario_estado']);
        Permission::create(['name' => 'usuario_login_notificacion']);

        //Sesiones de usuarios (solo Super Administrador)
        Permission::create(['name' => 'usuario_sessiones']);                 //Ver las sesiones abiertas de un usuario
        Permission::create(['name' => 'usuario_cerrar_session']);            //Cerrar una sesion puntual de un usuario
        Permission::create(['name' => 'usuario_cerrar_todas_sessiones']);    //Cerrar todas las sesiones de un usuario
        Permission::create(['name' => 'session_index']);                     //Ver las sesiones abiertas de todos los usuarios
        Permission::create(['name' => 'session_cerrar']);                    //Cerrar una sesion puntual desde la pantalla global

        //Dashboard
        Permission::create(['name' => 'dashboard']);

        //Perfil
        Permission::create(['name' => 'perfil_show']);
        Permission::create(['name' => 'perfil_edit']);
        Permission::create(['name' => 'perfil_edit_password']);
        Permission::create(['name' => 'perfil_cerrar_session']);

        //Auditar
        Permission::create(['name' => 'auditar_index']);
        Permission::create(['name' => 'auditar_show']);

        //Error log
        Permission::create(['name' => 'error_log_index']);
        Permission::create(['name' => 'error_log_show']);
        Permission::create(['name' => 'error_log_create']);
        Permission::create(['name' => 'error_log_estado']);

        //Ejemplos
        Permission::create(['name' => 'ejemplo_index']);
        Permission::create(['name' => 'ejemplo_show']);
        Permission::create(['name' => 'ejemplo_create']);
        Permission::create(['name' => 'ejemplo_store']);
        Permission::create(['name' => 'ejemplo_edit']);
        Permission::create(['name' => 'ejemplo_update']);
        Permission::create(['name' => 'ejemplo_destroy']);

        //Procesos en segundo plano
        Permission::create(['name' => 'queue_control_index']);
        Permission::create(['name' => 'queue_control_update_porcentaje']);

        //Lista de roles
        $superAdmin = Role::create(['name' => 'Super Administrador']);
        $admin = Role::create(['name' => 'Administrador']);
        $invitado =  Role::create(['name' => 'Invitado']);

        //El Super Administrador tiene todos los permisos del sistema,
        //incluida la gestion de sesiones que no se le da al Administrador
        $superAdmin->givePermissionTo(Permission::all());

        $admin->givePermissionTo([
            'permiso_index',
            'permiso_move',

            'role_index',
            'role_show',
            'role_create',
            'role_store',
            'role_edit',
            'role_update',
            'role_destroy',
            'role_move_permiso',

            'usuario_index',
            'usuario_show',
            'usuario_create',
            'usuario_store',
            'usuario_edit',
            'usuario_update',
            'usuario_estado',
            'usuario_login_notificacion',

            'dashboard',

            'perfil_show',
            'perfil_edit',
            'perfil_edit_password',
            'perfil_cerrar_session',

            'auditar_index',
            'auditar_show',

            'error_log_index',
            'error_log_show',
            'error_log_create',
            'error_log_estado',

            'ejemplo_index',
            'ejemplo_show',
            'ejemplo_create',
            'ejemplo_store',
            'ejemplo_edit',
            'ejemplo_update',
            'ejemplo_destroy',

            'queue_control_index',
            'queue_control_update_porcentaje',
        ]);

        $invitado->givePermissionTo([
            'dashboard',

            'perfil_show',
            'perfil_edit',
            'perfil_edit_password',
        ]);

        $user = User::find(1);
        $user->assignRole('Super Administrador');

    }

}
