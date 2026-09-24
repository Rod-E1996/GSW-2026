<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AuditarAccion;

class AuditarAccionesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $date = \Carbon\Carbon::now();

        $auditarAcciones = [
            ["nombre"=>"agregar", 'created_at' => $date, 'updated_at' => $date],
            ["nombre"=>"editar", 'created_at' => $date, 'updated_at' => $date],
            ["nombre"=>"eliminar", 'created_at' => $date, 'updated_at' => $date],
            ["nombre"=>"activar", 'created_at' => $date, 'updated_at' => $date],
            ["nombre"=>"desactivar", 'created_at' => $date, 'updated_at' => $date],
            ["nombre"=>"validar", 'created_at' => $date, 'updated_at' => $date],
            ["nombre"=>"rechazar", 'created_at' => $date, 'updated_at' => $date]
        ];

        AuditarAccion::insert($auditarAcciones);
    }
}
