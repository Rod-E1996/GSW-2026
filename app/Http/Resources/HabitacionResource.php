<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formatea una habitacion para el panel React.
 */
class HabitacionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'piso' => $this->piso,
            'estado_habitacion' => $this->estado_habitacion,
            'estado_nombre' => $this->estado_nombre,
            'descripcion' => $this->descripcion,
            'tipo_habitacion_id' => $this->tipo_habitacion_id,
            'tipo' => $this->tipoHabitacion?->nombre,
        ];
    }
}
