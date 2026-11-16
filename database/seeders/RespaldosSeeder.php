<?php

namespace Database\Seeders;

use App\Models\Respaldo;
use App\Models\User;
use App\Services\RespaldoService;
use Illuminate\Database\Seeder;

/**
 * Genera un respaldo real de la base recien sembrada (archivo .sql en
 * storage/app/respaldos), asi el modulo de respaldos tiene una fila que se
 * puede descargar. No se inventan respaldos antiguos: su descarga daria 404
 * porque el archivo no existiria.
 *
 * Va al final de DatabaseSeeder para que el volcado incluya todos los datos.
 * Solo corre si la tabla de respaldos esta vacia.
 */
class RespaldosSeeder extends Seeder
{
    public function run()
    {
        if (Respaldo::count() > 0) {
            return;
        }

        $admin = User::where('email', 'admin@gmail.com')->first();

        try {
            $respaldo = app(RespaldoService::class)->generar($admin, Respaldo::MANUAL);
            $this->command?->info('Respaldo inicial: ' . $respaldo->archivo . ' (' . round($respaldo->tamano / 1024) . ' KB).');
        } catch (\Throwable $e) {
            //Sin permisos de escritura en storage no se detiene la siembra
            $this->command?->warn('No se pudo generar el respaldo inicial: ' . $e->getMessage());
        }
    }
}
