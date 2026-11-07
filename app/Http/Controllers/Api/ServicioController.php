<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servicio;

/**
 * Catalogo publico de servicios complementarios (lo usa la pantalla de reserva).
 */
class ServicioController extends Controller
{
    public function index()
    {
        $servicios = Servicio::with('categoria')
            ->where('estado', 1)
            ->whereHas('categoria', fn ($q) => $q->where('estado', 1))
            ->get()
            ->sortBy([fn ($a, $b) => ($a->categoria->orden ?? 0) <=> ($b->categoria->orden ?? 0), ['nombre', 'asc']])
            ->values();

        return response()->json([
            'data' => $servicios->map(fn (Servicio $s) => [
                'id' => $s->id,
                'categoria' => $s->categoria->nombre,
                'nombre' => $s->nombre,
                'nombre_en' => $s->nombre_en,
                'descripcion' => $s->descripcion,
                'descripcion_en' => $s->descripcion_en,
                'imagen_url' => $s->imagen_url,
                'precio' => (float) $s->precio,
                'forma_cobro' => $s->forma_cobro,
                'forma_cobro_nombre' => $s->forma_cobro_nombre,
                'requiere_fecha' => $s->requiere_fecha,
            ]),
        ]);
    }
}
