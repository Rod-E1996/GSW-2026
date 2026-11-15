<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Valoracion;
use Illuminate\Http\Request;

/**
 * Valoraciones del lado del huesped (valorar su estadia) y del sitio publico
 * (las aprobadas y el promedio).
 */
class ValoracionController extends Controller
{
    //Ultimas valoraciones aprobadas y el promedio de todas las aprobadas
    public function index()
    {
        $aprobadas = Valoracion::where('estado', Valoracion::APROBADA);

        return response()->json([
            'promedio' => round((float) (clone $aprobadas)->avg('puntuacion'), 1),
            'total' => (clone $aprobadas)->count(),
            'data' => (clone $aprobadas)->with(['huesped', 'reserva.tipoHabitacion'])->latest('id')->limit(6)->get()
                ->map(fn (Valoracion $v) => [
                    'id' => $v->id,
                    'puntuacion' => $v->puntuacion,
                    'comentario' => $v->comentario,
                    'respuesta' => $v->respuesta,
                    'huesped' => $v->huesped?->nombres,
                    'habitacion' => $v->reserva?->tipoHabitacion?->nombre,
                    'fecha' => $v->created_at?->toDateString(),
                ]),
        ]);
    }

    //El huesped valora una estadia suya ya finalizada, una sola vez
    public function store(Request $request, string $codigo)
    {
        $reserva = Reserva::where('estado', 1)
            ->where('codigo', $codigo)
            ->whereHas('huesped', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();

        if (!$reserva) {
            return response()->json(['message' => 'No encontramos esa reserva en tu cuenta.'], 404);
        }
        if ((int) $reserva->estado_reserva !== Reserva::FINALIZADA) {
            return response()->json(['message' => 'Solo puedes valorar una estadía finalizada.'], 422);
        }
        if ($reserva->valoracion()->exists()) {
            return response()->json(['message' => 'Ya valoraste esta estadía.'], 422);
        }

        $datos = $request->validate([
            'puntuacion' => ['required', 'integer', 'min:1', 'max:5'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        $valoracion = $reserva->valoracion()->create($datos + [
            'huesped_id' => $reserva->huesped_id,
            'estado' => Valoracion::PENDIENTE,
        ]);

        return response()->json([
            'message' => 'Gracias por tu valoración. La publicaremos cuando el hotel la revise.',
            'data' => $valoracion,
        ], 201);
    }
}
