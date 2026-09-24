<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AuditarTabla;

class AuditarTablasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $date = \Carbon\Carbon::now();

        $auditarTabla = [
            ["tabla"=>"usuario", "modelo"=>"App\Models\User", 'created_at' => $date, 'updated_at' => $date],
            ["tabla"=>"perfil", "modelo"=>"App\Models\User", 'created_at' => $date, 'updated_at' => $date]
        ];

        AuditarTabla::insert($auditarTabla);
    }
}
