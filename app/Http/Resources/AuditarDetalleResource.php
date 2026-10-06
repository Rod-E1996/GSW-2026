<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Registro de auditoria para el panel React.
 */
class AuditarDetalleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'usuario' => $this->user?->name,
            'accion' => $this->auditarAccion?->nombre,
            'entidad' => $this->auditarTabla?->tabla,
            'nombre_modificado' => $this->nombre_modificado,
            'fecha' => $this->created_at,
            'antes' => $this->decodificar($this->antes),
            'despues' => $this->decodificar($this->despues),
        ];
    }

    //Los campos antes/despues se guardan como JSON; se devuelven ya decodificados.
    private function decodificar($valor)
    {
        if (is_null($valor)) {
            return null;
        }
        $decodificado = json_decode($valor, true);
        return json_last_error() === JSON_ERROR_NONE ? $decodificado : $valor;
    }
}
