<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API de los parametros del sistema para el panel React (consultar y actualizar).
 */
class ConfiguracionController extends Controller
{
    //Listado de parametros, en el orden en que se definieron.
    public function index()
    {
        return response()->json([
            'data' => Configuracion::orderBy('id')->get(['clave', 'valor', 'tipo', 'grupo', 'descripcion']),
        ]);
    }

    //Actualiza varios parametros a la vez; si alguno es invalido no se guarda ninguno.
    public function update(Request $request)
    {
        $request->validate([
            'valores' => ['required', 'array', 'min:1'],
        ]);

        $parametros = Configuracion::all()->keyBy('clave');
        $reglas = [];

        foreach ($request->input('valores') as $clave => $valor) {
            $reglas['valores.' . $clave] = isset($parametros[$clave])
                ? Configuracion::REGLAS_TIPO[$parametros[$clave]->tipo] ?? ['required', 'string', 'max:1000']
                : ['prohibited'];
        }

        $datos = $request->validate($reglas);

        DB::transaction(function () use ($datos, $parametros) {
            foreach ($datos['valores'] as $clave => $valor) {
                $parametro = $parametros[$clave];
                $parametro->valor = (string) $valor;
                $parametro->save();
            }
        });

        return response()->json([
            'message' => 'Parámetros actualizados con éxito.',
            'data' => Configuracion::orderBy('id')->get(['clave', 'valor', 'tipo', 'grupo', 'descripcion']),
        ]);
    }
}
