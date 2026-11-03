<?php

namespace App\Services;

use App\Models\Tarifa;
use App\Models\Temporada;
use App\Models\TipoHabitacion;
use Carbon\CarbonPeriod;

/**
 * Calcula el precio de una estadia noche por noche.
 * Cada noche usa la tarifa de su temporada; sin tarifa, precio base por multiplicador;
 * fuera de temporada, el precio base.
 */
class PrecioService
{
    //La fecha de salida no se cobra: la ultima noche es la anterior.
    public function cotizar(TipoHabitacion $tipo, string $llegada, string $salida): array
    {
        //Ya vienen ordenadas: la primera que cubra una fecha es la que manda (ver Temporada::vigentePara)
        $temporadas = Temporada::where('estado', 1)
            ->whereDate('fecha_inicio', '<', $salida)
            ->whereDate('fecha_fin', '>=', $llegada)
            ->orderByDesc('prioridad')
            ->orderByDesc('multiplicador')
            ->orderBy('id')
            ->get();

        $tarifas = Tarifa::where('estado', 1)
            ->where('tipo_habitacion_id', $tipo->id)
            ->whereIn('temporada_id', $temporadas->pluck('id'))
            ->pluck('precio_noche', 'temporada_id');

        $noches = [];

        foreach (CarbonPeriod::create($llegada, $salida)->excludeEndDate() as $dia) {
            $fecha = $dia->toDateString();

            $temporada = $temporadas->first(fn (Temporada $t) => $t->fecha_inicio->toDateString() <= $fecha
                && $t->fecha_fin->toDateString() >= $fecha);

            $noches[] = [
                'fecha' => $fecha,
                'temporada_id' => $temporada?->id,
                'temporada' => $temporada?->nombre,
                'precio_noche' => $this->precioNoche($tipo, $temporada, $tarifas),
            ];
        }

        $subtotal = round(array_sum(array_column($noches, 'precio_noche')), 2);

        return [
            'noches' => $noches,
            'subtotal' => $subtotal,
            'precio_promedio' => $noches ? round($subtotal / count($noches), 2) : 0.0,
        ];
    }

    private function precioNoche(TipoHabitacion $tipo, ?Temporada $temporada, $tarifas): float
    {
        if (!$temporada) {
            return (float) $tipo->precio_base;
        }

        return isset($tarifas[$temporada->id])
            ? (float) $tarifas[$temporada->id]
            : round((float) $tipo->precio_base * (float) $temporada->multiplicador, 2);
    }
}
