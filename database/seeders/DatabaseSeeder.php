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
        	//Siempre al final: el respaldo inicial vuelca todo lo anterior
        	RespaldosSeeder::class,
        ]);
    }
}
