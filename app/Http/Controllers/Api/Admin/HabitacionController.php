<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HabitacionResource;
use App\Models\Habitacion;
use App\Models\TipoHabitacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API de habitaciones para el panel React.
 */
class HabitacionController extends Controller
{
    //Listado paginado con filtros (numero, tipo, estado fisico).
    public function index(Request $request)
    {
        $query = Habitacion::query()->with('tipoHabitacion')->where('estado', 1);

        if ($numero = $request->get('numero')) {
            $query->where('numero', 'LIKE', "%$numero%");
        }
        if ($tipo = $request->get('tipo_habitacion_id')) {
            $query->where('tipo_habitacion_id', $tipo);
        }
        if ($estado = $request->get('estado_habitacion')) {
            $query->where('estado_habitacion', $estado);
        }

        return HabitacionResource::collection(
            $query->orderBy('piso')->orderBy('numero')->paginate(15)
        );
    }

    //Opciones de los filtros y del formulario.
    public function catalogos()
    {
        return response()->json([
            'tipos' => TipoHabitacion::where('estado', 1)->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => collect(Habitacion::ESTADOS)
                ->map(fn ($nombre, $valor) => ['valor' => $valor, 'nombre' => $nombre])
                ->values(),
        ]);
    }

    public function show($id)
    {
        $habitacion = Habitacion::with('tipoHabitacion')->where('estado', 1)->find($id);
        if (!$habitacion) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        return new HabitacionResource($habitacion);
    }

    //Crear. Si el numero era de una habitacion eliminada, se reactiva esa misma para conservar sus reservas.
    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        $datos['descripcion'] = $datos['descripcion'] ?? null;

        $habitacion = Habitacion::where('numero', $datos['numero'])->where('estado', 0)->first();

        if ($habitacion) {
            $habitacion->update($datos + ['estado' => 1]);
        } else {
            $habitacion = Habitacion::create($datos);
        }

        return (new HabitacionResource($habitacion->load('tipoHabitacion')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        $habitacion = Habitacion::where('estado', 1)->find($id);
        if (!$habitacion) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $datos = $request->validate($this->reglas($habitacion->id));

        if ((int) $datos['tipo_habitacion_id'] !== (int) $habitacion->tipo_habitacion_id && $habitacion->reservasPorAtender()->exists()) {
            return response()->json([
                'message' => 'No se puede cambiar el tipo: la habitación tiene reservas por atender.',
            ], 422);
        }

        $habitacion->update($datos);

        return new HabitacionResource($habitacion->load('tipoHabitacion'));
    }

    //Cambia solo el estado fisico (recepcion puede hacerlo sin poder editar la habitacion).
    public function estado(Request $request, $id)
    {
        $habitacion = Habitacion::where('estado', 1)->find($id);
        if (!$habitacion) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $datos = $request->validate([
            'estado_habitacion' => Habitacion::$rules['estado_habitacion'],
        ]);

        $habitacion->update($datos);

        return new HabitacionResource($habitacion->load('tipoHabitacion'));
    }

    //Eliminar (logico). No se puede si tiene reservas por atender.
    public function destroy($id)
    {
        $habitacion = Habitacion::where('estado', 1)->find($id);
        if (!$habitacion) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        if ($habitacion->reservasPorAtender()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: la habitación tiene reservas por atender.',
            ], 422);
        }

        $habitacion->estado = 0;
        $habitacion->save();

        return response()->json(['message' => 'Eliminado con éxito.']);
    }

    private function reglas($id = null): array
    {
        $reglas = Habitacion::rules($id);
        $reglas['tipo_habitacion_id'] = ['required', 'integer', Rule::exists('tipos_habitacion', 'id')->where('estado', 1)];

        return $reglas;
    }
}
