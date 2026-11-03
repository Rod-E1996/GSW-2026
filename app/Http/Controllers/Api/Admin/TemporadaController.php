<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemporadaResource;
use App\Models\Temporada;
use Illuminate\Http\Request;

/**
 * API de temporadas para el panel React.
 */
class TemporadaController extends Controller
{
    //Listado paginado: primero las mas recientes.
    public function index()
    {
        return TemporadaResource::collection(
            Temporada::where('estado', 1)->orderByDesc('fecha_inicio')->paginate(10)
        );
    }

    public function show($id)
    {
        $temporada = Temporada::where('estado', 1)->find($id);
        if (!$temporada) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        return new TemporadaResource($temporada);
    }

    public function store(Request $request)
    {
        $temporada = Temporada::create($request->validate(Temporada::rules()));

        return (new TemporadaResource($temporada))->response()->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        $temporada = Temporada::where('estado', 1)->find($id);
        if (!$temporada) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $temporada->update($request->validate(Temporada::rules($temporada->id)));

        return new TemporadaResource($temporada);
    }

    //Eliminar (logico). Sus tarifas dejan de mostrarse con ella.
    public function destroy($id)
    {
        $temporada = Temporada::where('estado', 1)->find($id);
        if (!$temporada) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $temporada->estado = 0;
        $temporada->save();

        return response()->json(['message' => 'Eliminado con éxito.']);
    }
}
