<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemporadaResource;
use App\Models\Tarifa;
use App\Models\Temporada;
use App\Models\TipoHabitacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * API de la matriz de precios por tipo de habitacion y temporada.
 */
class TarifaController extends Controller
{
    public function index()
    {
        return response()->json($this->matriz());
    }

    //Guarda varias celdas a la vez. Una celda vacia quita la tarifa: se usara precio base por multiplicador.
    public function update(Request $request)
    {
        $datos = $request->validate([
            'tarifas' => ['required', 'array', 'min:1', 'max:2000'],
            'tarifas.*.tipo_habitacion_id' => ['required', 'integer', Rule::exists('tipos_habitacion', 'id')->where('estado', 1)],
            'tarifas.*.temporada_id' => ['required', 'integer', Rule::exists('temporadas', 'id')->where('estado', 1)],
            'tarifas.*.precio_noche' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        DB::transaction(function () use ($datos) {
            foreach ($datos['tarifas'] as $celda) {
                $tarifa = Tarifa::firstOrNew([
                    'tipo_habitacion_id' => $celda['tipo_habitacion_id'],
                    'temporada_id' => $celda['temporada_id'],
                ]);

                $precio = $celda['precio_noche'] ?? null;

                if ($precio !== null) {
                    $tarifa->fill(['precio_noche' => $precio, 'estado' => 1])->save();
                } elseif ($tarifa->exists) {
                    $tarifa->fill(['estado' => 0])->save();
                }
            }
        });

        return response()->json(['message' => 'Tarifas guardadas con éxito.'] + $this->matriz());
    }

    //Tipos, temporadas que aun no terminan y sus precios.
    private function matriz(): array
    {
        $temporadas = Temporada::where('estado', 1)
            ->whereDate('fecha_fin', '>=', now()->toDateString())
            ->orderBy('fecha_inicio')
            ->get();

        return [
            'tipos' => TipoHabitacion::where('estado', 1)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'precio_base']),
            'temporadas' => TemporadaResource::collection($temporadas),
            'tarifas' => Tarifa::where('estado', 1)
                ->whereIn('temporada_id', $temporadas->pluck('id'))
                ->get(['tipo_habitacion_id', 'temporada_id', 'precio_noche']),
        ];
    }
}
