<?php

use Database\Seeders\ConfiguracionesSeeder;
use Database\Seeders\PermisosHotelSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Las bases ya instaladas no vuelven a ejecutar los seeders al arrancar, asi que
     * los permisos y parametros nuevos se registran tambien al migrar.
     */
    public function up()
    {
        (new PermisosHotelSeeder)->run();
        (new ConfiguracionesSeeder)->run();
    }

    public function down()
    {
        //Los permisos se conservan: pueden estar asignados a roles creados por el hotel.
    }
};
