<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueControl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Procesos en segundo plano (tabla queue_control) para el panel React.
 * Cada trabajo de la cola que registra su avance (SendEmail, SendEmailNuevoDispositivo)
 * aparece aqui con su progreso. Tambien informa cuantos trabajos esperan en la cola
 * y cuantos fallaron, para saber si el worker esta corriendo.
 */
class ProcesoController extends Controller
{
    //Un proceso sin avance en este tiempo se considera detenido (el worker murio a mitad)
    const MINUTOS_SIN_AVANCE = 10;

    public function index(Request $request)
    {
        $request->validate([
            'estado' => ['nullable', 'in:en_curso,terminados,detenidos'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $limite = now()->subMinutes(self::MINUTOS_SIN_AVANCE);

        $query = QueueControl::with('user')->where('estado', 1);

        if ($keyword = $request->get('search')) {
            $query->where('titulo', 'LIKE', "%$keyword%");
        }

        match ($request->get('estado')) {
            'en_curso' => $query->where('pendiente', 1)->where('updated_at', '>=', $limite),
            'detenidos' => $query->where('pendiente', 1)->where('updated_at', '<', $limite),
            'terminados' => $query->where('pendiente', 0),
            default => null,
        };

        $procesos = $query->orderByDesc('pendiente')->orderByDesc('id')->paginate(15);

        $base = QueueControl::where('estado', 1);

        return response()->json([
            'data' => $procesos->getCollection()->map(fn (QueueControl $p) => $this->fila($p, $limite)),
            'meta' => [
                'current_page' => $procesos->currentPage(),
                'last_page' => $procesos->lastPage(),
                'total' => $procesos->total(),
            ],
            'resumen' => [
                'en_curso' => (clone $base)->where('pendiente', 1)->where('updated_at', '>=', $limite)->count(),
                'detenidos' => (clone $base)->where('pendiente', 1)->where('updated_at', '<', $limite)->count(),
                'terminados' => (clone $base)->where('pendiente', 0)->count(),
                'hoy' => (clone $base)->whereDate('fecha_inicio', now()->toDateString())->count(),
                //Un trabajo que el worker ya tomo sigue en la tabla con reserved_at: no esta esperando
                'en_cola' => DB::table('jobs')->whereNull('reserved_at')->count(),
                'fallidos' => DB::table('failed_jobs')->count(),
                'cola' => config('queue.default'),
            ],
        ]);
    }

    private function fila(QueueControl $p, Carbon $limite): array
    {
        $total = max(1, (int) $p->total_procesos);
        $progreso = min((int) $p->progreso, $total);
        $pendiente = (int) $p->pendiente === 1;
        $inicio = $p->fecha_inicio ? Carbon::parse($p->fecha_inicio) : null;
        $fin = $p->fecha_fin ? Carbon::parse($p->fecha_fin) : null;

        return [
            'id' => $p->id,
            'titulo' => $p->titulo,
            'total' => (int) $p->total_procesos,
            'progreso' => $progreso,
            'porcentaje' => round($progreso / $total * 100, 1),
            'situacion' => !$pendiente ? 'terminado' : ($p->updated_at && $p->updated_at->lt($limite) ? 'detenido' : 'en_curso'),
            'fecha_inicio' => $inicio?->toIso8601String(),
            'fecha_fin' => $fin?->toIso8601String(),
            'ultimo_avance' => $p->updated_at?->toIso8601String(),
            'duracion_segundos' => $inicio ? (int) abs(($fin ?? now())->diffInSeconds($inicio)) : null,
            'usuario' => $p->user?->name,
        ];
    }
}
