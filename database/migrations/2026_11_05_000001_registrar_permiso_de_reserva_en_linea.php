<?php

use Database\Seeders\PermisosHotelSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Las bases ya instaladas no vuelven a ejecutar los seeders al arrancar,
     * asi que el permiso del huesped para reservar se registra al migrar.
     */
    public function up()
    {
        (new PermisosHotelSeeder)->run();
    }

    public function down()
    {
        //Los permisos se conservan: pueden estar asignados a roles creados por el hotel.
    }
};
