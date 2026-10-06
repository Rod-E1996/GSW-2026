<?php

namespace App\Http\Requests;

use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;
use Illuminate\Foundation\Http\FormRequest;

class StoreTipoHabitacionRequest extends FormRequest
{
    //Los permisos ya se validan en el middleware de la ruta (permission:tipo_habitacion_store)
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return TipoHabitacion::rules() + TipoHabitacionImagen::rules();
    }
}
