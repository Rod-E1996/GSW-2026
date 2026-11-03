<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formatea una temporada para el panel React.
 */
class TemporadaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'nombre_en' => $this->nombre_en,
            'fecha_inicio' => $this->fecha_inicio->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin->format('Y-m-d'),
            'multiplicador' => (float) $this->multiplicador,
            'prioridad' => $this->prioridad,
            'color' => $this->color,
        ];
    }
}
