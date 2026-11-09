<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Temporada;

/**
 * Seeder de temporadas (ajustan el precio base por fechas).
 * La temporada "Baja" (x1.00) es implicita: si ninguna temporada aplica a una
 * fecha, se usa el precio base tal cual. Solo se siembran las que cambian el precio.
 * Es seguro re-ejecutarlo: solo inserta si la tabla esta vacia.
 */
class TemporadasSeeder extends Seeder
{
    public function run()
    {
        if (Temporada::count() > 0) {
            return;
        }

        $temporadas = [
            //Historico: le da precios de temporada a las reservas de ejemplo ya pasadas
            ['nombre' => 'Media 2026',             'nombre_en' => 'Mid season 2026',       'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-03-27', 'multiplicador' => 1.15, 'prioridad' => 1, 'color' => '#2F80ED'],
            ['nombre' => 'Semana Santa 2026',      'nombre_en' => 'Holy Week 2026',        'fecha_inicio' => '2026-03-28', 'fecha_fin' => '2026-04-05', 'multiplicador' => 1.45, 'prioridad' => 2, 'color' => '#9B51E0'],
            ['nombre' => 'Fiestas agostinas 2026', 'nombre_en' => 'August holidays 2026',  'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-07', 'multiplicador' => 1.30, 'prioridad' => 2, 'color' => '#F2994A'],
            ['nombre' => 'Puente de Independencia 2026', 'nombre_en' => 'Independence weekend 2026', 'fecha_inicio' => '2026-09-12', 'fecha_fin' => '2026-09-15', 'multiplicador' => 1.20, 'prioridad' => 2, 'color' => '#27AE60'],

            ['nombre' => 'Fin de año 2026',        'nombre_en' => 'Year end 2026',         'fecha_inicio' => '2026-12-15', 'fecha_fin' => '2027-01-06', 'multiplicador' => 1.40, 'prioridad' => 2, 'color' => '#EB5757'],
            ['nombre' => 'Media 2027',             'nombre_en' => 'Mid season 2027',       'fecha_inicio' => '2027-02-01', 'fecha_fin' => '2027-03-20', 'multiplicador' => 1.15, 'prioridad' => 1, 'color' => '#2F80ED'],
            ['nombre' => 'Semana Santa 2027',      'nombre_en' => 'Holy Week 2027',        'fecha_inicio' => '2027-03-21', 'fecha_fin' => '2027-04-04', 'multiplicador' => 1.45, 'prioridad' => 2, 'color' => '#9B51E0'],
            ['nombre' => 'Fiestas agostinas 2027', 'nombre_en' => 'August holidays 2027',  'fecha_inicio' => '2027-08-01', 'fecha_fin' => '2027-08-07', 'multiplicador' => 1.30, 'prioridad' => 2, 'color' => '#F2994A'],
        ];

        foreach ($temporadas as $temporada) {
            Temporada::create($temporada);
        }
    }
}
