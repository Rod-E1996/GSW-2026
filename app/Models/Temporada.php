<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temporada extends Model
{
    protected $table = 'temporadas';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'nombre_en',
        'fecha_inicio',
        'fecha_fin',
        'multiplicador',
        'prioridad',
        'color',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'multiplicador' => 'decimal:2',
    ];

    // validaciones
    static $rules = [
        'nombre' => ['required', 'string', 'max:100'],
        'fecha_inicio' => ['required', 'date'],
        'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        'multiplicador' => ['required', 'numeric', 'min:0.1', 'max:9.99'],
        'nombre_en' => ['nullable', 'string', 'max:100'],
        'prioridad' => ['nullable', 'integer', 'min:0', 'max:9999'],
        'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
    ];

    public static function rules($id = null)
    {
        return self::$rules;
    }

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    // relaciones
    public function tarifas()
    {
        return $this->hasMany(Tarifa::class, 'temporada_id', 'id');
    }

    //Devuelve la temporada vigente para una fecha dada (o null si ninguna aplica).
    //Si varias se traslapan, gana la de mayor prioridad y, si empatan, la de mayor multiplicador.
    public static function vigentePara($fecha)
    {
        return self::where('estado', 1)
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha)
            ->orderByDesc('prioridad')
            ->orderByDesc('multiplicador')
            ->first();
    }
}
