<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Valoracion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

//Moderacion de valoraciones: listar, aprobar o rechazar y responder
class ValoracionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['estado' => ['nullable', Rule::in([Valoracion::PENDIENTE, Valoracion::APROBADA, Valoracion::RECHAZADA])]]);

        $valoraciones = Valoracion::with(['huesped', 'reserva', 'moderador'])
            ->when($request->get('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->latest('id')
            ->paginate(15);

        return response()->json([
            'data' => $valoraciones->getCollection()->map(fn (Valoracion $v) => $this->fila($v)),
            'meta' => ['current_page' => $valoraciones->currentPage(), 'last_page' => $valoraciones->lastPage(), 'total' => $valoraciones->total()],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $valoracion = Valoracion::findOrFail($id);

        $datos = $request->validate([
            'estado' => ['required', Rule::in([Valoracion::APROBADA, Valoracion::RECHAZADA])],
            'respuesta' => ['nullable', 'string', 'max:1000'],
        ]);

        $valoracion->update($datos + ['moderada_at' => now(), 'user_id' => $request->user()->id]);

        return response()->json(['message' => 'Valoración actualizada.', 'data' => $this->fila($valoracion->fresh(['huesped', 'reserva', 'moderador']))]);
    }

    private function fila(Valoracion $v): array
    {
        return [
            'id' => $v->id,
            'reserva' => $v->reserva?->codigo,
            'huesped' => $v->huesped?->nombre_completo,
            'puntuacion' => $v->puntuacion,
            'comentario' => $v->comentario,
            'estado' => $v->estado,
            'respuesta' => $v->respuesta,
            'moderador' => $v->moderador?->name,
            'fecha' => $v->created_at?->toIso8601String(),
        ];
    }
}
