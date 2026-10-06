<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Permiso del catalogo para el panel React. Incluye cuantos roles lo usan
 * (para proteger de borrado los permisos en uso).
 */
class PermisoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles_count' => $this->whenCounted('roles'),
        ];
    }
}
