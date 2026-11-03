<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoriaServicio;
use Illuminate\Http\Request;

/**
 * API de categorias de servicio para el panel React.
 */
class CategoriaServicioController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => CategoriaServicio::where('estado', 1)
                ->withCount(['servicios' => fn ($q) => $q->where('estado', 1)])
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function show($id)
    {
        $categoria = CategoriaServicio::where('estado', 1)->find($id);
        if (!$categoria) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        return response()->json(['data' => $categoria]);
    }

    public function store(Request $request)
    {
        $categoria = CategoriaServicio::create($request->validate(CategoriaServicio::rules()));

        return response()->json(['data' => $categoria], 201);
    }

    public function update(Request $request, $id)
    {
        $categoria = CategoriaServicio::where('estado', 1)->find($id);
        if (!$categoria) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $categoria->update($request->validate(CategoriaServicio::rules($categoria->id)));

        return response()->json(['data' => $categoria]);
    }

    //Eliminar (logico). No se puede si tiene servicios activos.
    public function destroy($id)
    {
        $categoria = CategoriaServicio::where('estado', 1)->find($id);
        if (!$categoria) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $activos = $categoria->servicios()->where('estado', 1)->count();
        if ($activos > 0) {
            return response()->json([
                'message' => "No se puede eliminar: hay $activos servicio(s) en esta categoría.",
            ], 422);
        }

        $categoria->estado = 0;
        $categoria->save();

        return response()->json(['message' => 'Eliminado con éxito.']);
    }
}
