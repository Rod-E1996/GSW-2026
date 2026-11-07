<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\HabitacionNoDisponible;
use App\Http\Controllers\Controller;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Services\CotizacionService;
use App\Services\DisponibilidadService;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reserva en linea del usuario autenticado. Queda pendiente hasta registrar el anticipo.
 */
class ReservaController extends Controller
{
    public function __construct(
        private ReservaService $reservas,
        private DisponibilidadService $disponibilidad,
        private CotizacionService $cotizaciones
    ) {
    }

    //Resumen que la pantalla muestra antes de confirmar. No guarda nada.
    public function cotizar(Request $request)
    {
        $datos = $request->validate($this->reglasEstadia(), $this->mensajes());
        $habitacion = Habitacion::with('tipoHabitacion')->find($datos['habitacion_id']);
        $personas = $this->validarEstadia($datos, $habitacion);

        $cotizacion = $this->cotizaciones->calcular(
            $habitacion->tipoHabitacion,
            $datos['fecha_llegada'],
            $datos['fecha_salida'],
            $personas,
            $datos['servicios'] ?? []
        );

        return response()->json([
            'data' => $cotizacion + [
                'habitacion' => [
                    'id' => $habitacion->id,
                    'numero' => $habitacion->numero,
                    'tipo' => $habitacion->tipoHabitacion->nombre,
                    'capacidad' => $habitacion->tipoHabitacion->capacidad,
                    'imagen_principal' => $habitacion->tipoHabitacion->imagenPrincipal?->url,
                ],
                'disponible' => $this->disponibilidad
                    ->habitacionesLibres($datos['fecha_llegada'], $datos['fecha_salida'], $habitacion->tipo_habitacion_id)
                    ->contains('id', $habitacion->id),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglasEstadia() + [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'tipo_documento' => ['required', 'string', 'max:20'],
            'documento' => ['required', 'string', 'max:50'],
            'telefono' => ['required', 'string', 'max:30'],
            'hora_estimada_llegada' => Reserva::$rules['hora_estimada_llegada'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], $this->mensajes());

        $habitacion = Habitacion::with('tipoHabitacion')->find($datos['habitacion_id']);
        $this->validarEstadia($datos, $habitacion);

        //El titular es siempre la ficha de la cuenta; se actualiza con lo que escribio
        $ficha = Huesped::paraUsuario($request->user());
        $ficha->update(array_intersect_key($datos, array_flip(['nombres', 'apellidos', 'tipo_documento', 'documento', 'telefono'])));

        try {
            $reserva = $this->reservas->crear(
                array_intersect_key($datos, array_flip(['habitacion_id', 'fecha_llegada', 'fecha_salida', 'adultos', 'ninos', 'hora_estimada_llegada', 'notas'])) + [
                    'huesped_id' => $ficha->id,
                    'user_id' => $request->user()->id,
                    'canal' => Reserva::CANAL_WEB,
                ],
                $datos['servicios'] ?? []
            );
        } catch (HabitacionNoDisponible $e) {
            //Flujo alterno 4a del caso de uso: se informa y se ofrecen otras opciones
            return response()->json([
                'message' => $e->getMessage(),
                'alternativas' => $this->disponibilidad->alternativas(
                    $datos['fecha_llegada'],
                    $datos['fecha_salida'],
                    (int) $datos['adultos'] + (int) ($datos['ninos'] ?? 0),
                    $habitacion->tipo_habitacion_id
                ),
            ], 409);
        }

        return response()->json([
            'message' => 'Reserva registrada. Queda pendiente hasta registrar el anticipo.',
            'data' => [
                'codigo' => $reserva->codigo,
                'estado' => $reserva->estado_nombre,
                'fecha_llegada' => $reserva->fecha_llegada->toDateString(),
                'fecha_salida' => $reserva->fecha_salida->toDateString(),
                'subtotal_alojamiento' => (float) $reserva->subtotal_alojamiento,
                'subtotal_servicios' => (float) $reserva->subtotal_servicios,
                'impuesto' => (float) $reserva->impuesto,
                'total' => (float) $reserva->total,
                'anticipo_requerido' => (float) $reserva->anticipo_requerido,
            ],
        ], 201);
    }

    private function reglasEstadia(): array
    {
        return [
            'habitacion_id' => ['required', 'integer', Rule::exists('habitaciones', 'id')->where('estado', 1)],
            'fecha_llegada' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'fecha_salida' => ['required', 'date_format:Y-m-d', 'after:fecha_llegada'],
            'adultos' => Reserva::$rules['adultos'],
            'ninos' => Reserva::$rules['ninos'],
            'servicios' => ['nullable', 'array', 'max:20'],
            'servicios.*.servicio_id' => ['required', 'integer', 'distinct'],
            'servicios.*.cantidad' => ['nullable', 'integer', 'min:1', 'max:20'],
            'servicios.*.fecha_servicio' => ['nullable', 'date_format:Y-m-d'],
            'servicios.*.hora_servicio' => ['nullable', 'date_format:H:i'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'fecha_llegada.after_or_equal' => 'La llegada no puede ser una fecha pasada.',
            'fecha_salida.after' => 'La salida debe ser posterior a la llegada.',
            'servicios.*.servicio_id.distinct' => 'Ese servicio está repetido.',
        ];
    }

    //Devuelve el numero de personas, que tambien define el precio de algunos servicios.
    private function validarEstadia(array $datos, Habitacion $habitacion): int
    {
        $noches = (int) Carbon::parse($datos['fecha_llegada'])->diffInDays(Carbon::parse($datos['fecha_salida']));

        if ($noches > Reserva::MAX_NOCHES) {
            throw ValidationException::withMessages([
                'fecha_salida' => 'La estadía no puede superar las ' . Reserva::MAX_NOCHES . ' noches.',
            ]);
        }

        $personas = (int) $datos['adultos'] + (int) ($datos['ninos'] ?? 0);
        $capacidad = $habitacion->tipoHabitacion->capacidad;

        if ($personas > $capacidad) {
            throw ValidationException::withMessages([
                'adultos' => "Esta habitación admite hasta $capacidad persona(s).",
            ]);
        }

        return $personas;
    }
}
