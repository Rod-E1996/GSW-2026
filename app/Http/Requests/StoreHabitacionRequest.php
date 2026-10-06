<?php

namespace App\Http\Requests;

use App\Models\Habitacion;
use Illuminate\Foundation\Http\FormRequest;

class StoreHabitacionRequest extends FormRequest
{
    //Los permisos ya se validan en el middleware de la ruta (permission:habitacion_store)
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return Habitacion::rules();
    }
}
