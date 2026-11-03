<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Habitacion;
use App\Models\Reserva;
use App\Models\TipoHabitacion;
use App\Services\DisponibilidadService;
use App\Services\PrecioService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * API publica de disponibilidad: habitaciones libres para unas fechas.
 */
class DisponibilidadController extends Controller
{
    public function __construct(
        private DisponibilidadService $disponibilidad,
        private PrecioService $precios
    ) {
    }

    public function index(Request $request)
    {
        $datos = $request->validate([
            'llegada' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'salida' => ['required', 'date_format:Y-m-d', 'after:llegada'],
            'huespedes' => ['required', 'integer', 'min:1', 'max:20'],
            'tipo_habitacion_id' => ['nullable', 'integer'],
            'capacidad' => ['nullable', 'integer', 'min:1', 'max:20'],
            'precio_min' => ['nullable', 'numeric', 'min:0'],
            'precio_max' => ['nullable', 'numeric', 'min:0'],
        ], [
            'llegada.after_or_equal' => 'La llegada no puede ser una fecha pasada.',
            'salida.after' => 'La salida debe ser posterior a la llegada.',
        ]);

        $noches = (int) Carbon::parse($datos['llegada'])->diffInDays(Carbon::parse($datos['salida']));

        if ($noches > Reserva::MAX_NOCHES) {
            throw ValidationException::withMessages([
                'salida' => 'La estadía no puede superar las ' . Reserva::MAX_NOCHES . ' noches.',
            ]);
        }

        $huespedes = (int) $datos['huespedes'];
        $tipoId = isset($datos['tipo_habitacion_id']) ? (int) $datos['tipo_habitacion_id'] : null;

        //Primero lo que cabe en las fechas; los filtros de la pantalla actuan sobre este conjunto
        $ofertas = $this->ofertas($datos['llegada'], $datos['salida'], $huespedes);
        $delTipo = $ofertas->when($tipoId, fn ($o) => $o->where('tipo.id', $tipoId))->values();

        return response()->json([
            'data' => $this->filtrar($delTipo, $datos),
            'meta' => [
                'llegada' => $datos['llegada'],
                'salida' => $datos['salida'],
                'noches' => $noches,
                'huespedes' => $huespedes,
            ],
            //Todos los tipos, para que el elegido siga en la lista aunque no tenga disponibilidad
            'filtros' => [
                'tipos' => TipoHabitacion::where('estado', 1)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
                'capacidades' => $ofertas->pluck('tipo.capacidad')->unique()->sort()->values(),
            ],
            //Sin nada para las fechas y el tipo pedidos, se ofrecen otras opciones (los filtros no cuentan)
            'alternativas' => $delTipo->isEmpty()
                ? $this->disponibilidad->alternativas($datos['llegada'], $datos['salida'], $huespedes, $tipoId)
                : null,
        ]);
    }

    private function ofertas(string $llegada, string $salida, int $huespedes): Collection
    {
        $cotizaciones = [];

        return $this->disponibilidad->habitacionesLibres($llegada, $salida)
            ->filter(fn (Habitacion $h) => $h->tipoHabitacion->capacidad >= $huespedes)
            ->map(function (Habitacion $h) use (&$cotizaciones, $llegada, $salida) {
                //Las habitaciones del mismo tipo cuestan lo mismo: basta cotizar una vez por tipo
                $cotizacion = $cotizaciones[$h->tipo_habitacion_id]
                    ??= $this->precios->cotizar($h->tipoHabitacion, $llegada, $salida);

                return [
                    'id' => $h->id,
                    'numero' => $h->numero,
                    'piso' => $h->piso,
                    'tipo' => [
                        'id' => $h->tipoHabitacion->id,
                        'nombre' => $h->tipoHabitacion->nombre,
                        'nombre_en' => $h->tipoHabitacion->nombre_en,
                        'capacidad' => $h->tipoHabitacion->capacidad,
                        'descripcion' => $h->tipoHabitacion->descripcion,
                        'descripcion_en' => $h->tipoHabitacion->descripcion_en,
                        'imagen_principal' => $h->tipoHabitacion->imagenPrincipal?->url,
                    ],
                    'precio_noche' => $cotizacion['precio_promedio'],
                    'total' => $cotizacion['subtotal'],
                    'noches' => array_map(fn (array $n) => [
                        'fecha' => $n['fecha'],
                        'temporada' => $n['temporada'],
                        'precio_noche' => $n['precio_noche'],
                    ], $cotizacion['noches']),
                ];
            })
            ->sortBy([['total', 'asc'], ['numero', 'asc']])
            ->values();
    }

    private function filtrar(Collection $ofertas, array $filtros): Collection
    {
        return $ofertas
            ->when($filtros['capacidad'] ?? null, fn ($o, $valor) => $o->where('tipo.capacidad', '>=', (int) $valor))
            ->when(isset($filtros['precio_min']), fn ($o) => $o->where('precio_noche', '>=', (float) $filtros['precio_min']))
            ->when(isset($filtros['precio_max']), fn ($o) => $o->where('precio_noche', '<=', (float) $filtros['precio_max']))
            ->values();
    }
}
