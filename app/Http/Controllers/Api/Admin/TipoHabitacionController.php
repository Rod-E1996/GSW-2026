<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTipoHabitacionRequest;
use App\Http\Requests\UpdateTipoHabitacionRequest;
use App\Http\Resources\TipoHabitacionResource;
use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;
use App\Services\TipoHabitacionImagenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API de administracion de tipos de habitacion (consumida por el panel React).
 * Los permisos se aplican por ruta con el middleware permission:...,sanctum.
 */
class TipoHabitacionController extends Controller
{
    public function __construct(private TipoHabitacionImagenService $imagenes)
    {
    }

    //Listado paginado con filtros (nombre, capacidad).
    public function index(Request $request)
    {
        $query = TipoHabitacion::query()->with('imagenPrincipal')->where('estado', 1);

        if ($nombre = $request->get('nombre')) {
            $query->where('nombre', 'LIKE', "%$nombre%");
        }
        if ($capacidad = $request->get('capacidad')) {
            $query->where('capacidad', '>=', $capacidad);
        }

        return TipoHabitacionResource::collection(
            $query->orderBy('nombre')->paginate(10)
        );
    }

    //Detalle con sus imagenes.
    public function show($id)
    {
        $tipo = TipoHabitacion::with('imagenes')->where('estado', 1)->find($id);
        if (!$tipo) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        return new TipoHabitacionResource($tipo);
    }

    //Crear (acepta imagenes opcionales por multipart).
    public function store(StoreTipoHabitacionRequest $request)
    {
        $tipo = DB::transaction(function () use ($request) {
            $tipo = TipoHabitacion::create(
                $request->safe()->only(['nombre', 'capacidad', 'precio_base', 'descripcion'])
            );
            $this->imagenes->guardar($tipo, $request->file('imagenes', []));
            return $tipo;
        });

        return (new TipoHabitacionResource($tipo->load('imagenes')))
            ->response()
            ->setStatusCode(201);
    }

    //Actualizar los datos (las imagenes se gestionan en endpoints aparte).
    public function update(UpdateTipoHabitacionRequest $request, $id)
    {
        $tipo = TipoHabitacion::where('estado', 1)->find($id);
        if (!$tipo) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $tipo->update($request->safe()->only(['nombre', 'capacidad', 'precio_base', 'descripcion']));

        return new TipoHabitacionResource($tipo->load('imagenes'));
    }

    //Eliminar (logico). No se puede si tiene habitaciones activas.
    public function destroy($id)
    {
        $tipo = TipoHabitacion::where('estado', 1)->find($id);
        if (!$tipo) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $habitacionesActivas = $tipo->habitaciones()->where('estado', 1)->count();
        if ($habitacionesActivas > 0) {
            return response()->json([
                'message' => "No se puede eliminar: hay $habitacionesActivas habitación(es) de este tipo.",
            ], 422);
        }

        $tipo->estado = 0;
        $tipo->save();

        return response()->json(['message' => 'Eliminado con éxito.']);
    }

    //Subir imagenes nuevas a un tipo existente.
    public function imagenesStore(Request $request, $id)
    {
        $tipo = TipoHabitacion::where('estado', 1)->find($id);
        if (!$tipo) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $request->validate(TipoHabitacionImagen::rules());
        $this->imagenes->guardar($tipo, $request->file('imagenes', []));

        return new TipoHabitacionResource($tipo->load('imagenes'));
    }

    //Eliminar una imagen.
    public function imagenDestroy($id, $imagenId)
    {
        $imagen = TipoHabitacionImagen::where('tipo_habitacion_id', $id)->find($imagenId);
        if (!$imagen) {
            return response()->json(['message' => 'La imagen no existe.'], 404);
        }

        $this->imagenes->eliminar($imagen);

        return response()->json(['message' => 'Imagen eliminada.']);
    }

    //Marcar una imagen como principal.
    public function imagenPrincipal($id, $imagenId)
    {
        $imagen = TipoHabitacionImagen::where('tipo_habitacion_id', $id)->find($imagenId);
        if (!$imagen) {
            return response()->json(['message' => 'La imagen no existe.'], 404);
        }

        $this->imagenes->marcarPrincipal($imagen);

        return response()->json(['message' => 'Imagen principal actualizada.']);
    }
}
