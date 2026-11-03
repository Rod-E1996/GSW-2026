<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BloqueoHabitacionResource;
use App\Models\BloqueoHabitacion;
use App\Models\Habitacion;
use App\Services\ReservaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * API de bloqueos de habitacion para el panel React.
 */
class BloqueoHabitacionController extends Controller
{
    //Listado paginado. Por defecto solo muestra los que aun no terminan.
    public function index(Request $request)
    {
        $query = BloqueoHabitacion::query()->with(['habitacion', 'user'])->where('estado', 1);

        if ($habitacion = $request->get('habitacion_id')) {
            $query->where('habitacion_id', $habitacion);
        }
        if (!$request->boolean('terminados')) {
            $query->whereDate('fecha_fin', '>=', now()->toDateString());
        }

        return BloqueoHabitacionResource::collection(
            $query->orderBy('fecha_inicio')->orderBy('id')->paginate(15)
        )->additional([
            'habitaciones' => Habitacion::where('estado', 1)->orderBy('numero')->get(['id', 'numero']),
            'motivos' => collect(BloqueoHabitacion::MOTIVOS)
                ->map(fn ($nombre, $valor) => ['valor' => $valor, 'nombre' => $nombre])
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        $bloqueo = DB::transaction(function () use ($datos, $request) {
            $this->comprobarFechas($datos);

            return BloqueoHabitacion::create($datos + ['user_id' => $request->user()->id]);
        }, ReservaService::INTENTOS);

        return (new BloqueoHabitacionResource($bloqueo->load(['habitacion', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        $bloqueo = BloqueoHabitacion::where('estado', 1)->find($id);
        if (!$bloqueo) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $datos = $this->validar($request);

        DB::transaction(function () use ($datos, $bloqueo) {
            $this->comprobarFechas($datos, $bloqueo->id);
            $bloqueo->update($datos);
        }, ReservaService::INTENTOS);

        return new BloqueoHabitacionResource($bloqueo->load(['habitacion', 'user']));
    }

    //Eliminar (logico).
    public function destroy($id)
    {
        $bloqueo = BloqueoHabitacion::where('estado', 1)->find($id);
        if (!$bloqueo) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $bloqueo->estado = 0;
        $bloqueo->save();

        return response()->json(['message' => 'Eliminado con éxito.']);
    }

    private function validar(Request $request): array
    {
        $reglas = BloqueoHabitacion::$rules;
        $reglas['habitacion_id'][] = Rule::exists('habitaciones', 'id')->where('estado', 1);

        return $request->validate($reglas);
    }

    //Un bloqueo no puede chocar con otro ni dejar sin habitacion a una reserva vigente.
    //La fila queda bloqueada para que no entre una reserva entre esta revision y el guardado.
    private function comprobarFechas(array $datos, $id = null): void
    {
        $habitacion = Habitacion::whereKey($datos['habitacion_id'])->lockForUpdate()->first();

        $repetido = BloqueoHabitacion::where('estado', 1)
            ->where('habitacion_id', $datos['habitacion_id'])
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->whereDate('fecha_inicio', '<=', $datos['fecha_fin'])
            ->whereDate('fecha_fin', '>=', $datos['fecha_inicio'])
            ->exists();

        if ($repetido) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'La habitación ya tiene un bloqueo en esas fechas.',
            ]);
        }

        $reservas = $habitacion
            ->reservasActivasEntre($datos['fecha_inicio'], $datos['fecha_fin'])
            ->lockForUpdate()
            ->pluck('codigo');

        if ($reservas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'La habitación tiene reservas en esas fechas: ' . $reservas->implode(', ') . '.',
            ]);
        }
    }
}
