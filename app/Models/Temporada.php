<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temporada extends Model
{
    protected $table = 'temporadas';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'multiplicador',
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

    //Devuelve la temporada vigente para una fecha dada (o null si ninguna aplica).
    //Si varias se traslapan, gana la de mayor multiplicador (la mas especifica/cara).
    public static function vigentePara($fecha)
    {
        return self::where('estado', 1)
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha)
            ->orderByDesc('multiplicador')
            ->first();
    }
}
