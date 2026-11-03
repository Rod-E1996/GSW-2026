<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\HabitacionNoDisponible;
use App\Http\Controllers\Controller;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Reserva;
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
        private DisponibilidadService $disponibilidad
    ) {
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'habitacion_id' => ['required', 'integer', Rule::exists('habitaciones', 'id')->where('estado', 1)],
            'fecha_llegada' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'fecha_salida' => ['required', 'date_format:Y-m-d', 'after:fecha_llegada'],
            'adultos' => Reserva::$rules['adultos'],
            'ninos' => Reserva::$rules['ninos'],
            'hora_estimada_llegada' => Reserva::$rules['hora_estimada_llegada'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [
            'fecha_llegada.after_or_equal' => 'La llegada no puede ser una fecha pasada.',
            'fecha_salida.after' => 'La salida debe ser posterior a la llegada.',
        ]);

        $huespedes = (int) $datos['adultos'] + (int) ($datos['ninos'] ?? 0);
        $this->validarEstadia($datos, $huespedes);

        try {
            $reserva = $this->reservas->crear($datos + [
                'huesped_id' => Huesped::paraUsuario($request->user())->id,
                'user_id' => $request->user()->id,
                'canal' => Reserva::CANAL_WEB,
            ]);
        } catch (HabitacionNoDisponible $e) {
            //Flujo alterno 4a del caso de uso: se informa y se ofrecen otras opciones
            return response()->json([
                'message' => $e->getMessage(),
                'alternativas' => $this->disponibilidad->alternativas(
                    $datos['fecha_llegada'],
                    $datos['fecha_salida'],
                    $huespedes,
                    Habitacion::whereKey($datos['habitacion_id'])->value('tipo_habitacion_id')
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
                'impuesto' => (float) $reserva->impuesto,
                'total' => (float) $reserva->total,
                'anticipo_requerido' => (float) $reserva->anticipo_requerido,
            ],
        ], 201);
    }

    private function validarEstadia(array $datos, int $huespedes): void
    {
        $noches = (int) Carbon::parse($datos['fecha_llegada'])->diffInDays(Carbon::parse($datos['fecha_salida']));

        if ($noches > Reserva::MAX_NOCHES) {
            throw ValidationException::withMessages([
                'fecha_salida' => 'La estadía no puede superar las ' . Reserva::MAX_NOCHES . ' noches.',
            ]);
        }

        $capacidad = Habitacion::with('tipoHabitacion')->find($datos['habitacion_id'])->tipoHabitacion->capacidad;

        if ($huespedes > $capacidad) {
            throw ValidationException::withMessages([
                'adultos' => "Esta habitación admite hasta $capacidad persona(s).",
            ]);
        }
    }
}
