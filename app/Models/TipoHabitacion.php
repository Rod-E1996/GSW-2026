<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class TipoHabitacion extends Model
{
    protected $table = 'tipos_habitacion';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'capacidad',
        'precio_base',
        'descripcion',
        'estado'
    ];

    // validaciones
    static $rules = [
        'nombre' => ['required', 'string', 'max:100'],
        'capacidad' => ['required', 'integer', 'min:1', 'max:20'],
        'precio_base' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        'descripcion' => ['nullable', 'string'],
    ];

    //Reglas con validacion de nombre unico entre los tipos activos ($id se ignora al editar)
    public static function rules($id = null)
    {
        $rules = self::$rules;
        $rules['nombre'][] = Rule::unique('tipos_habitacion', 'nombre')
            ->where('estado', 1)
            ->ignore($id);

        return $rules;
    }

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    // relaciones
    // Nota: cuando exista el modelo Habitacion, agregar:
    // public function habitaciones(){ return $this->hasMany(Habitacion::class, 'tipo_habitacion_id', 'id'); }
}
