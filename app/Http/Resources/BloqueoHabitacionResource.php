<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formatea un bloqueo de habitacion para el panel React.
 */
class BloqueoHabitacionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'habitacion_id' => $this->habitacion_id,
            'habitacion' => $this->habitacion?->numero,
            'fecha_inicio' => $this->fecha_inicio->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin->format('Y-m-d'),
            'motivo' => $this->motivo,
            'motivo_nombre' => $this->motivo_nombre,
            'descripcion' => $this->descripcion,
            'registrado_por' => $this->user?->name,
        ];
    }
}
