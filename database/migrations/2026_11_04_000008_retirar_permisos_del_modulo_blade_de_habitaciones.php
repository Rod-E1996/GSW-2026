<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Estos permisos solo abrian los formularios Blade, que ya no existen.
     * Crear y editar en React usan habitacion_store y habitacion_update.
     */
    public function up()
    {
        Permission::whereIn('name', ['habitacion_create', 'habitacion_edit'])->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down()
    {
        //No se restauran: ninguna ruta los usa.
    }
};
