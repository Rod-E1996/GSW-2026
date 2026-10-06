<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rol para el panel React. En el listado incluye el conteo de permisos;
 * en el detalle (show) se adjuntan los permisos asignados y disponibles.
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Disponible cuando el index usa withCount('permissions').
            'permisos_count' => $this->whenCounted('permissions'),
        ];
    }
}
