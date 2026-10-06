<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representacion de un registro del error log para el panel React.
 */
class ErrorLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario' => $this->user->name ?? null,
            'controller' => $this->controller,
            'mensaje' => $this->mensaje,
            'parametros' => $this->parametros,               // JSON en crudo (string)
            'parametros_json' => $this->decodeParametros(),  // objeto si el JSON es valido
            'estado' => (int) $this->estado,                 // 0 sin resolver, 1 resuelto
            'fecha' => $this->created_at,
        ];
    }

    //Intenta decodificar los parametros a objeto; si no es JSON valido devuelve null.
    private function decodeParametros()
    {
        if (empty($this->parametros)) {
            return null;
        }

        $data = json_decode($this->parametros, true);

        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }
}
