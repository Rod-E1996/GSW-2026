<?php

namespace App\Http\Requests;

use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTipoHabitacionRequest extends FormRequest
{
    //Los permisos ya se validan en el middleware de la ruta (permission:tipo_habitacion_update)
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        //Se ignora el propio id para que el nombre unico no choque consigo mismo al editar
        return TipoHabitacion::rules($this->route('id')) + TipoHabitacionImagen::rules();
    }
}
