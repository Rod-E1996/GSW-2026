<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        $this->call([
            AuditarAccionesSeeder::class,
        	AuditarTablasSeeder::class,
        	PermissionSeeder::class,
        	PermisosHotelSeeder::class,
        	RolesHotelSeeder::class,
        	ConfiguracionesSeeder::class,
        	TiposHabitacionSeeder::class,
        	TiposHabitacionImagenesSeeder::class,
        	HabitacionesSeeder::class,
        	ServiciosSeeder::class,
        	TemporadasSeeder::class,
        	DemoHotelSeeder::class,
        	ValoracionesSeeder::class,
        	ActividadSistemaSeeder::class,
        	EjemplosSeeder::class,
        ]);

        //Garantia: el Super Administrador queda con todos los permisos que existan,
        //sin importar el orden de los seeders o los modulos que se agreguen despues
        \Spatie\Permission\Models\Role::where('name', 'Super Administrador')->first()
            ?->syncPermissions(\Spatie\Permission\Models\Permission::all());
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        //Siempre al final: el respaldo inicial vuelca todo lo anterior
        $this->call(RespaldosSeeder::class);
    }
}
