<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formatea un tipo de habitacion para el API (lo que consume el front React).
 */
class TipoHabitacionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'capacidad' => $this->capacidad,
            'precio_base' => (float) $this->precio_base,
            'descripcion' => $this->descripcion,
            'imagen_principal' => $this->imagenPrincipal?->url,
            'imagenes' => $this->whenLoaded('imagenes', function () {
                return $this->imagenes->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $img->url,
                    'principal' => (bool) $img->principal,
                ]);
            }),
        ];
    }
}
