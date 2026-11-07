<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Huesped;
use Illuminate\Http\Request;

/**
 * Busqueda y alta de huespedes para la reserva telefonica o presencial.
 */
class HuespedController extends Controller
{
    //Busca por documento, correo, telefono o nombre. Devuelve pocas coincidencias: es un buscador, no un listado.
    public function index(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:150']]);
        $q = trim($request->get('q'));

        $huespedes = Huesped::where('estado', 1)
            ->where(function ($w) use ($q) {
                $w->where('documento', 'LIKE', "%$q%")
                    ->orWhere('email', 'LIKE', "%$q%")
                    ->orWhere('telefono', 'LIKE', "%$q%")
                    ->orWhereRaw(ReservaController::nombreCompletoSql() . ' LIKE ?', ["%$q%"]);
            })
            ->withCount('reservas')
            ->orderBy('nombres')
            ->limit(10)
            ->get();

        return response()->json(['data' => $huespedes->map(fn (Huesped $h) => $this->datos($h))]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'tipo_documento' => ['required', 'string', 'max:20'],
            'documento' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150', 'required_without:telefono'],
            'telefono' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'nacionalidad' => ['nullable', 'string', 'max:80'],
        ], [
            'email.required_without' => 'Anota un correo o un teléfono para contactar al huésped.',
            'telefono.required_without' => 'Anota un correo o un teléfono para contactar al huésped.',
        ]);

        //Una persona, una ficha: si el documento ya existe se usa la ficha que hay
        $existente = Huesped::where('estado', 1)
            ->where('tipo_documento', $datos['tipo_documento'])
            ->where('documento', $datos['documento'])
            ->first();

        if ($existente) {
            return response()->json([
                'message' => "Ya existe un huésped con ese documento: {$existente->nombre_completo}.",
                'errors' => ['documento' => ["Ya existe un huésped con ese documento: {$existente->nombre_completo}."]],
                'existente' => $this->datos($existente),
            ], 422);
        }

        return response()->json(['data' => $this->datos(Huesped::create($datos))], 201);
    }

    private function datos(Huesped $h): array
    {
        return [
            'id' => $h->id,
            'nombres' => $h->nombres,
            'apellidos' => $h->apellidos,
            'nombre_completo' => $h->nombre_completo,
            'tipo_documento' => $h->tipo_documento,
            'documento' => $h->documento,
            'email' => $h->email,
            'telefono' => $h->telefono,
            'tiene_cuenta' => $h->user_id !== null,
            'reservas' => $h->reservas_count ?? $h->reservas()->count(),
        ];
    }
}
