<?php

namespace App\Services;

use App\Models\BloqueoHabitacion;
use App\Models\Habitacion;
use App\Models\Reserva;
use App\Models\TipoHabitacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Regla central del proyecto: que habitaciones estan libres en un intervalo.
 * Dos estadias se traslapan si la entrada de cada una es anterior a la salida de la otra,
 * asi que un huesped puede salir y otro entrar el mismo dia.
 */
class DisponibilidadService
{
    //Cuantas fechas alternativas se sugieren y hasta cuantos dias se buscan
    const FECHAS_SUGERIDAS = 3;
    const DIAS_DE_BUSQUEDA = 14;

    public function __construct(private PrecioService $precios)
    {
    }

    /**
     * Habitaciones que se pueden ofrecer. Cada reserva sin habitacion asignada
     * ocupa un lugar de su tipo, por eso se retira una habitacion por cada una.
     */
    public function habitacionesLibres(string $llegada, string $salida, ?int $tipoId = null, ?int $excluirReservaId = null): Collection
    {
        $libres = $this->libres($llegada, $salida, $tipoId, false, $excluirReservaId);
        $sinAsignar = $this->reservasVigentes($llegada, $salida, $tipoId, false, $excluirReservaId)
            ->whereNull('habitacion_id')
            ->countBy('tipo_habitacion_id');

        return $libres->groupBy('tipo_habitacion_id')
            ->flatMap(function (Collection $delTipo, $tipo) use ($sinAsignar) {
                return $delTipo->slice(0, max(0, $delTipo->count() - ($sinAsignar[$tipo] ?? 0)));
            })
            ->values();
    }

    /**
     * Revalida una habitacion concreta con lecturas bloqueantes. Solo tiene sentido dentro
     * de la transaccion que ya bloqueo las habitaciones del tipo (ver ReservaService).
     * $excluirReservaId: al modificar una reserva, la propia no cuenta como ocupacion.
     */
    public function sePuedeReservar(Habitacion $habitacion, string $llegada, string $salida, ?int $excluirReservaId = null): bool
    {
        $libres = $this->libres($llegada, $salida, $habitacion->tipo_habitacion_id, true, $excluirReservaId);
        $sinAsignar = $this->reservasVigentes($llegada, $salida, $habitacion->tipo_habitacion_id, true, $excluirReservaId)
            ->whereNull('habitacion_id')
            ->count();

        return $libres->contains('id', $habitacion->id) && $libres->count() > $sinAsignar;
    }

    //Lo que queda libre en las mismas fechas y las mismas noches en fechas cercanas.
    public function alternativas(string $llegada, string $salida, int $huespedes, ?int $tipoId = null): array
    {
        return [
            'tipos' => $this->tiposAlternativos($llegada, $salida, $huespedes),
            'fechas' => $this->fechasAlternativas($llegada, $salida, $huespedes, $tipoId),
        ];
    }

    private function libres(string $llegada, string $salida, ?int $tipoId, bool $bloquear = false, ?int $excluir = null): Collection
    {
        $habitaciones = Habitacion::with('tipoHabitacion')
            ->where('estado', 1)
            ->where('estado_habitacion', '!=', Habitacion::MANTENIMIENTO)
            ->whereHas('tipoHabitacion', fn ($q) => $q->where('estado', 1))
            ->when($tipoId, fn ($q) => $q->where('tipo_habitacion_id', $tipoId))
            ->orderBy('numero')
            ->get();

        $ocupadas = $this->reservasVigentes($llegada, $salida, $tipoId, $bloquear, $excluir)
            ->pluck('habitacion_id')
            ->filter();

        //fecha_fin es el ultimo dia bloqueado, inclusive
        $bloqueadas = BloqueoHabitacion::where('estado', 1)
            ->whereIn('habitacion_id', $habitaciones->pluck('id'))
            ->whereDate('fecha_inicio', '<', $salida)
            ->whereDate('fecha_fin', '>=', $llegada)
            ->when($bloquear, fn ($q) => $q->lockForUpdate())
            ->pluck('habitacion_id');

        return $habitaciones->reject(fn (Habitacion $h) => $ocupadas->contains($h->id) || $bloqueadas->contains($h->id))
            ->values();
    }

    private function reservasVigentes(string $llegada, string $salida, ?int $tipoId, bool $bloquear = false, ?int $excluir = null): Collection
    {
        return Reserva::vigentes()
            ->when($excluir, fn ($q) => $q->whereKeyNot($excluir))
            ->whereDate('fecha_llegada', '<', $salida)
            ->whereDate('fecha_salida', '>', $llegada)
            ->when($tipoId, fn ($q) => $q->where('tipo_habitacion_id', $tipoId))
            ->when($bloquear, fn ($q) => $q->lockForUpdate())
            ->get(['id', 'habitacion_id', 'tipo_habitacion_id']);
    }

    //Incluye el tipo pedido: si otro huesped tomo una habitacion, puede quedar otra igual.
    private function tiposAlternativos(string $llegada, string $salida, int $huespedes): array
    {
        return $this->habitacionesLibres($llegada, $salida)
            ->filter(fn (Habitacion $h) => $h->tipoHabitacion->capacidad >= $huespedes)
            ->groupBy('tipo_habitacion_id')
            ->map(function (Collection $delTipo) use ($llegada, $salida) {
                $tipo = $delTipo->first()->tipoHabitacion;
                $cotizacion = $this->precios->cotizar($tipo, $llegada, $salida);

                return [
                    'id' => $tipo->id,
                    'nombre' => $tipo->nombre,
                    'capacidad' => $tipo->capacidad,
                    'disponibles' => $delTipo->count(),
                    'precio_noche' => $cotizacion['precio_promedio'],
                    'total' => $cotizacion['subtotal'],
                ];
            })
            ->sortBy('total')
            ->values()
            ->all();
    }

    //Mueve la estadia un dia a la vez, primero hacia adelante, sin pasar de hoy.
    private function fechasAlternativas(string $llegada, string $salida, int $huespedes, ?int $tipoId): array
    {
        $hoy = Carbon::today();
        $noches = (int) Carbon::parse($llegada)->diffInDays(Carbon::parse($salida));
        $fechas = [];

        for ($dia = 1; $dia <= self::DIAS_DE_BUSQUEDA && count($fechas) < self::FECHAS_SUGERIDAS; $dia++) {
            foreach ([$dia, -$dia] as $desplazamiento) {
                $inicio = Carbon::parse($llegada)->addDays($desplazamiento);
                if ($inicio->lt($hoy) || count($fechas) === self::FECHAS_SUGERIDAS) {
                    continue;
                }

                $fin = (clone $inicio)->addDays($noches);
                $disponibles = $this->habitacionesLibres($inicio->toDateString(), $fin->toDateString(), $tipoId)
                    ->filter(fn (Habitacion $h) => $h->tipoHabitacion->capacidad >= $huespedes)
                    ->count();

                if ($disponibles > 0) {
                    $fechas[] = [
                        'llegada' => $inicio->toDateString(),
                        'salida' => $fin->toDateString(),
                        'disponibles' => $disponibles,
                    ];
                }
            }
        }

        return collect($fechas)->sortBy('llegada')->values()->all();
    }
}
