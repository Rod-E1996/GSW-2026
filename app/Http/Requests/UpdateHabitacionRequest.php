<?php

namespace App\Http\Requests;

use App\Models\Habitacion;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHabitacionRequest extends FormRequest
{
    //Los permisos ya se validan en el middleware de la ruta (permission:habitacion_update)
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        //Se ignora el propio id para que el numero unico no choque consigo mismo al editar
        return Habitacion::rules($this->route('id'));
    }
}
