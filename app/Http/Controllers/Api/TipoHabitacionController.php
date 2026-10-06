<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TipoHabitacionResource;
use App\Models\TipoHabitacion;

/**
 * API publica de tipos de habitacion (la consume el portal React).
 */
class TipoHabitacionController extends Controller
{
    //Lista los tipos activos con su imagen principal, del mas economico al mas caro.
    public function index()
    {
        $tipos = TipoHabitacion::with(['imagenPrincipal', 'imagenes'])
            ->where('estado', 1)
            ->orderBy('precio_base')
            ->get();

        return TipoHabitacionResource::collection($tipos);
    }
}
