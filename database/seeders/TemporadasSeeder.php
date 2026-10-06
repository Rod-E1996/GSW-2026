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
            ['nombre' => 'Media',            'fecha_inicio' => '2027-02-01', 'fecha_fin' => '2027-03-20', 'multiplicador' => 1.15],
            ['nombre' => 'Semana Santa',     'fecha_inicio' => '2027-03-21', 'fecha_fin' => '2027-04-04', 'multiplicador' => 1.45],
            ['nombre' => 'Fiestas agostinas','fecha_inicio' => '2027-08-01', 'fecha_fin' => '2027-08-07', 'multiplicador' => 1.30],
            ['nombre' => 'Fin de año',       'fecha_inicio' => '2026-12-15', 'fecha_fin' => '2027-01-06', 'multiplicador' => 1.40],
        ];

        foreach ($temporadas as $temporada) {
            Temporada::create($temporada);
        }
    }
}
