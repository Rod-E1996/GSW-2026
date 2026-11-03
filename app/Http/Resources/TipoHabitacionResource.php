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
            'nombre_en' => $this->nombre_en,
            'slug' => $this->slug,
            'orden' => $this->orden,
            'capacidad' => $this->capacidad,
            'precio_base' => (float) $this->precio_base,
            'descripcion' => $this->descripcion,
            'descripcion_en' => $this->descripcion_en,
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
