<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BloqueoHabitacionResource;
use App\Models\BloqueoHabitacion;
use App\Models\Habitacion;
use Illuminate\Http\Request;
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

        $bloqueo = BloqueoHabitacion::create($datos + ['user_id' => $request->user()->id]);

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

        $bloqueo->update($this->validar($request, $bloqueo->id));

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

    //Un bloqueo no puede chocar con otro bloqueo ni dejar sin habitacion a una reserva vigente.
    private function validar(Request $request, $id = null): array
    {
        $reglas = BloqueoHabitacion::$rules;
        $reglas['habitacion_id'][] = Rule::exists('habitaciones', 'id')->where('estado', 1);

        $datos = $request->validate($reglas);

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

        $reservas = Habitacion::find($datos['habitacion_id'])
            ->reservasActivasEntre($datos['fecha_inicio'], $datos['fecha_fin'])
            ->pluck('codigo');

        if ($reservas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'La habitación tiene reservas en esas fechas: ' . $reservas->implode(', ') . '.',
            ]);
        }

        return $datos;
    }
}
