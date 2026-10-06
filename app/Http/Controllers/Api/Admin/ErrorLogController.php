<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorLogResource;
use App\Models\ErrorLog;
use App\Traits\SaveErrorLogTrait;
use Illuminate\Http\Request;

/**
 * API del error log para el panel React (listar, ver, resolver y generar prueba).
 */
class ErrorLogController extends Controller
{
    //Listado paginado con busqueda por controller, mensaje, parametros o usuario.
    public function index(Request $request)
    {
        $query = ErrorLog::query()->with('user');

        if ($keyword = $request->get('search')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('controller', 'LIKE', "%$keyword%")
                    ->orWhere('mensaje', 'LIKE', "%$keyword%")
                    ->orWhere('parametros', 'LIKE', "%$keyword%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'LIKE', "%$keyword%"));
            });
        }

        // Primero los no resueltos (estado 0), luego los mas recientes.
        $query->orderBy('estado', 'asc')->orderBy('id', 'desc');

        return ErrorLogResource::collection($query->paginate(10));
    }

    //Detalle de un error puntual.
    public function show($id)
    {
        $error = ErrorLog::with('user')->find($id);

        if (!$error) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        return new ErrorLogResource($error);
    }

    //Marca un error como resuelto (estado = 1).
    public function estado($id)
    {
        $error = ErrorLog::find($id);

        if (!$error) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        $error->estado = 1;
        $error->save();

        return new ErrorLogResource($error->load('user'));
    }

    //Genera un registro de prueba en el error log (util para demostrar el modulo).
    public function prueba()
    {
        try {
            throw new \Exception('Registro de prueba generado manualmente desde el panel.');
        } catch (\Exception $e) {
            SaveErrorLogTrait::saveErrorLog(\Route::current(), $e);
        }

        return response()->json(['message' => 'Registro de prueba generado en el error log.']);
    }
}
