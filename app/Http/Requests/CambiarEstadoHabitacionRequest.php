<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoHabitacionRequest extends FormRequest
{
    //Los permisos ya se validan en el middleware de la ruta (permission:habitacion_estado)
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        //1 = disponible, 2 = ocupada, 3 = mantenimiento
        return [
            'estado_habitacion' => ['required', 'integer', 'in:1,2,3'],
        ];
    }
}
