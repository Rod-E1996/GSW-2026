<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuario para el modulo de administracion (lista y detalle/edicion).
 * Distinto de UserResource (ese es para la sesion/auth del propio usuario).
 */
class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'estado' => (int) $this->estado,
            'login_notificacion' => (int) $this->login_notificacion,
            'roles' => $this->getRoleNames(),
            'roles_ids' => $this->roles->pluck('id')->values(),
        ];
    }
}
