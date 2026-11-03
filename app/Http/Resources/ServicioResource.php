<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formatea un servicio complementario para el panel React.
 */
class ServicioResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'categoria_servicio_id' => $this->categoria_servicio_id,
            'categoria' => $this->categoria?->nombre,
            'nombre' => $this->nombre,
            'nombre_en' => $this->nombre_en,
            'descripcion' => $this->descripcion,
            'descripcion_en' => $this->descripcion_en,
            'icono' => $this->icono,
            'imagen_url' => $this->imagen_url,
            'precio' => (float) $this->precio,
            'forma_cobro' => $this->forma_cobro,
            'forma_cobro_nombre' => $this->forma_cobro_nombre,
            'requiere_fecha' => $this->requiere_fecha,
        ];
    }
}
