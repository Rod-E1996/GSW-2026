<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicioResource;
use App\Models\Servicio;
use Illuminate\Http\Request;

/**
 * API del catalogo de servicios complementarios para el panel React.
 */
class ServicioController extends Controller
{
    //Listado paginado con filtros (nombre, categoria).
    public function index(Request $request)
    {
        $query = Servicio::query()->with('categoria')->where('estado', 1);

        if ($nombre = $request->get('nombre')) {
            $query->where('nombre', 'LIKE', "%$nombre%");
        }
        if ($categoria = $request->get('categoria_servicio_id')) {
            $query->where('categoria_servicio_id', $categoria);
        }

        return ServicioResource::collection($query->orderBy('nombre')->paginate(15))->additional([
            'formas_cobro' => collect(Servicio::FORMAS_COBRO)
                ->map(fn ($nombre, $valor) => ['valor' => $valor, 'nombre' => $nombre])
                ->values(),
        ]);
    }

    public function show($id)
    {
        $servicio = Servicio::with('categoria')->where('estado', 1)->find($id);
        if (!$servicio) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        return new ServicioResource($servicio);
    }

    public function store(Request $request)
    {
        $servicio = Servicio::create($request->validate(Servicio::rules()));

        return (new ServicioResource($servicio->load('categoria')))->response()->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        $servicio = Servicio::where('estado', 1)->find($id);
        if (!$servicio) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $servicio->update($request->validate(Servicio::rules($servicio->id)));

        return new ServicioResource($servicio->load('categoria'));
    }

    //Eliminar (logico). Las reservas que ya lo contrataron conservan su detalle.
    public function destroy($id)
    {
        $servicio = Servicio::where('estado', 1)->find($id);
        if (!$servicio) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $servicio->estado = 0;
        $servicio->save();

        return response()->json(['message' => 'Eliminado con éxito.']);
    }

    //Sube o reemplaza la imagen del servicio.
    public function imagenStore(Request $request, $id)
    {
        $servicio = Servicio::where('estado', 1)->find($id);
        if (!$servicio) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $request->validate([
            'imagen' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $servicio->eliminarImagen();
        $servicio->imagen = $request->file('imagen')->store(Servicio::CARPETA, Servicio::DISCO);
        $servicio->save();

        return new ServicioResource($servicio->load('categoria'));
    }

    public function imagenDestroy($id)
    {
        $servicio = Servicio::where('estado', 1)->find($id);
        if (!$servicio) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $servicio->eliminarImagen();
        $servicio->imagen = null;
        $servicio->save();

        return new ServicioResource($servicio->load('categoria'));
    }
}
