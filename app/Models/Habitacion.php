<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Habitacion extends Model
{
    protected $table = 'habitaciones';
    protected $primaryKey = 'id';

    //Estados operativos de la habitacion
    const DISPONIBLE = 1;
    const OCUPADA = 2;
    const MANTENIMIENTO = 3;

    //Catalogo de estados: [valor => [etiqueta, color bootstrap]]
    const ESTADOS = [
        self::DISPONIBLE    => ['nombre' => 'Disponible',    'color' => 'success'],
        self::OCUPADA       => ['nombre' => 'Ocupada',       'color' => 'warning'],
        self::MANTENIMIENTO => ['nombre' => 'Mantenimiento', 'color' => 'secondary'],
    ];

    protected $fillable = [
        'numero',
        'piso',
        'estado_habitacion',
        'descripcion',
        'tipo_habitacion_id',
        'estado'
    ];

    // validaciones
    static $rules = [
        'numero' => ['required', 'string', 'max:10'],
        'piso' => ['required', 'integer', 'min:0', 'max:50'],
        'tipo_habitacion_id' => ['required', 'integer', 'exists:tipos_habitacion,id'],
        'estado_habitacion' => ['required', 'integer', 'in:1,2,3'],
        'descripcion' => ['nullable', 'string'],
    ];

    //Reglas con validacion de numero unico entre las habitaciones activas ($id se ignora al editar)
    public static function rules($id = null)
    {
        $rules = self::$rules;
        $rules['numero'][] = Rule::unique('habitaciones', 'numero')
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

    // accesores
    public function getEstadoNombreAttribute()
    {
        return self::ESTADOS[$this->estado_habitacion]['nombre'] ?? 'Desconocido';
    }

    public function getEstadoColorAttribute()
    {
        return self::ESTADOS[$this->estado_habitacion]['color'] ?? 'dark';
    }

    // relaciones
    public function tipoHabitacion()
    {
        return $this->belongsTo(TipoHabitacion::class, 'tipo_habitacion_id', 'id');
    }
}
