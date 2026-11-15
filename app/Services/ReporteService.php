<?php

namespace App\Services;

use App\Models\Habitacion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\TipoHabitacion;
use App\Models\Valoracion;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de ocupacion e ingresos de un periodo (opcionalmente de un tipo de habitacion).
 * - Noches vendidas: de reservas confirmadas, en estadia o finalizadas.
 * - Noches disponibles: habitaciones activas por los dias del periodo.
 * - ADR = ingreso por alojamiento / noches vendidas. RevPAR = ingreso / noches disponibles.
 * - Cobrado: pagos aprobados en el periodo.
 */
class ReporteService
{
    const MAX_DIAS = 366;
    const ESTADOS_VENDIDOS = [Reserva::CONFIRMADA, Reserva::EN_ESTADIA, Reserva::FINALIZADA];

    public function generar(string $desde, string $hasta, ?int $tipoId = null): array
    {
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->startOfDay();
        $dias = (int) $inicio->diffInDays($fin) + 1;

        $habitaciones = Habitacion::where('estado', 1)
            ->when($tipoId, fn ($q) => $q->where('tipo_habitacion_id', $tipoId))
            ->count();

        //Noches vendidas e ingreso por dia
        $porDia = DB::table('reserva_noches')
            ->join('reservas', 'reservas.id', '=', 'reserva_noches.reserva_id')
            ->where('reservas.estado', 1)
            ->whereIn('reservas.estado_reserva', self::ESTADOS_VENDIDOS)
            ->whereDate('reserva_noches.fecha', '>=', $inicio->toDateString())
            ->whereDate('reserva_noches.fecha', '<=', $fin->toDateString())
            ->when($tipoId, fn ($q) => $q->where('reservas.tipo_habitacion_id', $tipoId))
            ->selectRaw('reserva_noches.fecha as fecha, count(*) as vendidas, sum(reserva_noches.precio_noche) as ingreso')
            ->groupBy('reserva_noches.fecha')
            ->get()
            ->keyBy(fn ($fila) => substr((string) $fila->fecha, 0, 10));

        $serie = [];
        foreach (CarbonPeriod::create($inicio, $fin) as $dia) {
            $fila = $porDia[$dia->toDateString()] ?? null;
            $serie[] = ['fecha' => $dia->toDateString()]
                + $this->indicadores($habitaciones, (int) ($fila->vendidas ?? 0), (float) ($fila->ingreso ?? 0));
        }

        $vendidas = (int) $porDia->sum('vendidas');
        $ingreso = (float) $porDia->sum('ingreso');
        $servicios = $this->servicios($inicio, $fin, $tipoId);

        return [
            'periodo' => ['desde' => $inicio->toDateString(), 'hasta' => $fin->toDateString(), 'dias' => $dias],
            'tipo' => $tipoId ? TipoHabitacion::whereKey($tipoId)->value('nombre') : null,
            'resumen' => $this->indicadores($habitaciones * $dias, $vendidas, $ingreso) + [
                'habitaciones' => $habitaciones,
                'ingreso_servicios' => round(array_sum(array_column($servicios, 'ingreso')), 2),
                //Promedio de las valoraciones aprobadas recibidas en el periodo
                'valoracion_promedio' => round((float) $this->valoraciones($inicio, $fin, $tipoId)->avg('puntuacion'), 1),
                'valoraciones' => $this->valoraciones($inicio, $fin, $tipoId)->count(),
                'ingresos_cobrados' => round((float) Pago::aprobados()
                    ->whereBetween('pagado_at', [$inicio, (clone $fin)->endOfDay()])
                    ->when($tipoId, fn ($q) => $q->whereHas('reserva', fn ($r) => $r->where('tipo_habitacion_id', $tipoId)))
                    ->sum('monto'), 2),
            ],
            'serie' => $serie,
            'servicios' => array_slice($servicios, 0, 10),
        ];
    }

    private function valoraciones(Carbon $inicio, Carbon $fin, ?int $tipoId)
    {
        return Valoracion::where('estado', Valoracion::APROBADA)
            ->whereBetween('created_at', [$inicio, (clone $fin)->endOfDay()])
            ->when($tipoId, fn ($q) => $q->whereHas('reserva', fn ($r) => $r->where('tipo_habitacion_id', $tipoId)));
    }

    private function indicadores(int $disponibles, int $vendidas, float $ingreso): array
    {
        return [
            'noches_disponibles' => $disponibles,
            'noches_vendidas' => $vendidas,
            'ocupacion' => $disponibles ? round($vendidas * 100 / $disponibles, 1) : 0.0,
            'ingreso_alojamiento' => round($ingreso, 2),
            'adr' => $vendidas ? round($ingreso / $vendidas, 2) : 0.0,
            'revpar' => $disponibles ? round($ingreso / $disponibles, 2) : 0.0,
        ];
    }

    //Servicios mas solicitados (por su fecha de servicio o, si no tiene, por la llegada)
    private function servicios(Carbon $inicio, Carbon $fin, ?int $tipoId): array
    {
        return DB::table('reserva_servicio')
            ->join('reservas', 'reservas.id', '=', 'reserva_servicio.reserva_id')
            ->join('servicios', 'servicios.id', '=', 'reserva_servicio.servicio_id')
            ->where('reservas.estado', 1)
            ->whereIn('reservas.estado_reserva', self::ESTADOS_VENDIDOS)
            ->whereRaw('date(coalesce(reserva_servicio.fecha_servicio, reservas.fecha_llegada)) between ? and ?', [
                $inicio->toDateString(), $fin->toDateString(),
            ])
            ->when($tipoId, fn ($q) => $q->where('reservas.tipo_habitacion_id', $tipoId))
            ->groupBy('servicios.id', 'servicios.nombre')
            ->selectRaw('servicios.id, servicios.nombre, sum(reserva_servicio.cantidad) as cantidad, sum(reserva_servicio.subtotal) as ingreso')
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'nombre' => $s->nombre,
                'cantidad' => (int) $s->cantidad,
                'ingreso' => round((float) $s->ingreso, 2),
            ])
            ->all();
    }
}
