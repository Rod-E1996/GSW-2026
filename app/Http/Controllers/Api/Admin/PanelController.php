<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BloqueoHabitacion;
use App\Models\Habitacion;
use App\Models\Pago;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Panel de control de recepcion e informacion del calendario de ocupacion.
 */
class PanelController extends Controller
{
    public function index()
    {
        $hoy = now()->toDateString();
        $manana = now()->addDay()->toDateString();

        $habitaciones = Habitacion::where('estado', 1)->where('estado_habitacion', '!=', Habitacion::MANTENIMIENTO)->count();

        //Ocupan esta noche: reservas vigentes cuya estadia incluye hoy
        $estaNoche = Reserva::vigentes()->whereDate('fecha_llegada', '<=', $hoy)->whereDate('fecha_salida', '>', $hoy)->get(['id', 'habitacion_id']);
        $ocupadas = $estaNoche->whereNotNull('habitacion_id')->pluck('habitacion_id')->unique()->count()
            + $estaNoche->whereNull('habitacion_id')->count();

        $con = ['huesped', 'tipoHabitacion', 'habitacion'];

        $entradas = Reserva::with($con)->where('estado', 1)
            ->whereDate('fecha_llegada', $hoy)
            ->where(fn ($q) => $q->whereIn('id', Reserva::vigentes()->select('id'))->orWhereNotNull('check_in_at'))
            ->whereIn('estado_reserva', [Reserva::PENDIENTE, Reserva::CONFIRMADA, Reserva::EN_ESTADIA])
            ->orderBy('hora_estimada_llegada')->get();

        //Salidas de hoy y estadias que ya debieron salir
        $salidas = Reserva::with($con)->where('estado', 1)
            ->where('estado_reserva', Reserva::EN_ESTADIA)
            ->whereDate('fecha_salida', '<=', $hoy)
            ->orderBy('fecha_salida')->get();

        $proximas = Reserva::with($con)->vigentes()
            ->whereIn('estado_reserva', [Reserva::PENDIENTE, Reserva::CONFIRMADA])
            ->whereDate('fecha_llegada', '>=', $manana)
            ->whereDate('fecha_llegada', '<=', now()->addDays(7)->toDateString())
            ->orderBy('fecha_llegada')->limit(10)->get();

        $recientes = Reserva::with($con)->where('estado', 1)->latest('id')->limit(8)->get();

        return response()->json([
            'indicadores' => [
                'habitaciones' => $habitaciones,
                'ocupadas' => $ocupadas,
                'ocupacion' => $habitaciones ? round($ocupadas * 100 / $habitaciones, 1) : 0,
                'en_estadia' => Reserva::where('estado', 1)->where('estado_reserva', Reserva::EN_ESTADIA)->count(),
                'reservas_activas' => Reserva::vigentes()->whereDate('fecha_salida', '>', $hoy)->count(),
                'pendientes' => Reserva::vigentes()->where('estado_reserva', Reserva::PENDIENTE)->count(),
                'entradas_hoy' => $entradas->count(),
                'entradas_registradas' => $entradas->where('estado_reserva', Reserva::EN_ESTADIA)->count(),
                'salidas_hoy' => $salidas->count(),
                'ingresos_mes' => round((float) Pago::aprobados()
                    ->whereBetween('pagado_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('monto'), 2),
                'mes' => now()->locale('es')->translatedFormat('F Y'),
            ],
            'entradas' => $entradas->map(fn ($r) => $this->fila($r)),
            'salidas' => $salidas->map(fn ($r) => $this->fila($r)),
            'proximas' => $proximas->map(fn ($r) => $this->fila($r)),
            'recientes' => $recientes->map(fn ($r) => $this->fila($r)),
        ]);
    }

    //Mes de ocupacion: habitaciones, estadias, bloqueos y reservas sin habitacion asignada.
    public function calendario(Request $request)
    {
        $request->validate(['mes' => ['nullable', 'date_format:Y-m']]);
        $inicio = Carbon::createFromFormat('Y-m-d', ($request->get('mes') ?? now()->format('Y-m')) . '-01')->startOfDay();
        $fin = (clone $inicio)->endOfMonth()->startOfDay();

        $habitaciones = Habitacion::with('tipoHabitacion')->where('estado', 1)->orderBy('piso')->orderBy('numero')->get();

        //Estadias del mes: vigentes, en curso y terminadas (las canceladas y vencidas no ocupan)
        $estadias = Reserva::with('huesped')
            ->where('estado', 1)
            ->where(fn ($q) => $q->whereIn('id', Reserva::vigentes()->select('id'))->orWhere('estado_reserva', Reserva::FINALIZADA))
            ->whereDate('fecha_llegada', '<=', $fin->toDateString())
            ->whereDate('fecha_salida', '>', $inicio->toDateString())
            ->orderBy('fecha_llegada')
            ->get();

        $bloqueos = BloqueoHabitacion::where('estado', 1)
            ->whereDate('fecha_inicio', '<=', $fin->toDateString())
            ->whereDate('fecha_fin', '>=', $inicio->toDateString())
            ->get();

        $estadia = fn (Reserva $r) => [
            'codigo' => $r->codigo,
            'habitacion_id' => $r->habitacion_id,
            'llegada' => $r->fecha_llegada->toDateString(),
            //Una estadia terminada antes de tiempo ocupo hasta el dia en que salio
            'salida' => (int) $r->estado_reserva === Reserva::FINALIZADA && $r->check_out_at && $r->check_out_at->lt($r->fecha_salida)
                ? max($r->fecha_llegada->toDateString(), $r->check_out_at->toDateString())
                : $r->fecha_salida->toDateString(),
            'estado' => $r->estado_reserva,
            'estado_nombre' => $r->estado_nombre,
            'titular' => $r->huesped?->nombre_completo,
        ];

        return response()->json([
            'mes' => $inicio->format('Y-m'),
            'titulo' => ucfirst($inicio->locale('es')->translatedFormat('F Y')),
            'hoy' => now()->toDateString(),
            'dias' => $inicio->daysInMonth,
            'habitaciones' => $habitaciones->map(fn (Habitacion $h) => [
                'id' => $h->id,
                'numero' => $h->numero,
                'tipo' => $h->tipoHabitacion?->nombre,
                'estado_habitacion' => $h->estado_habitacion,
                'estado_nombre' => $h->estado_nombre,
            ]),
            'estadias' => $estadias->whereNotNull('habitacion_id')->map($estadia)->values(),
            'sin_asignar' => $estadias->whereNull('habitacion_id')->map($estadia)->values(),
            'bloqueos' => $bloqueos->map(fn ($b) => [
                'habitacion_id' => $b->habitacion_id,
                'inicio' => Carbon::parse($b->fecha_inicio)->toDateString(),
                'fin' => Carbon::parse($b->fecha_fin)->toDateString(),
                'motivo' => $b->motivo,
            ]),
        ]);
    }

    private function fila(Reserva $r): array
    {
        return [
            'codigo' => $r->codigo,
            'estado' => $r->estado_reserva,
            'estado_nombre' => $r->estado_nombre,
            'titular' => $r->huesped?->nombre_completo,
            'tipo' => $r->tipoHabitacion?->nombre,
            'habitacion' => $r->habitacion?->numero,
            'fecha_llegada' => $r->fecha_llegada->toDateString(),
            'fecha_salida' => $r->fecha_salida->toDateString(),
            'hora_estimada_llegada' => $r->hora_estimada_llegada ? substr($r->hora_estimada_llegada, 0, 5) : null,
            'total' => (float) $r->total,
            'canal' => $r->canal,
        ];
    }
}
