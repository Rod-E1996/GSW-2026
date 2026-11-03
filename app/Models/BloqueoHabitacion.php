<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloqueoHabitacion extends Model
{
    protected $table = 'bloqueos_habitacion';
    protected $primaryKey = 'id';

    //Motivos de bloqueo
    const MANTENIMIENTO = 'mantenimiento';
    const USO_INTERNO = 'uso_interno';

    //Catalogo: [valor => etiqueta]
    const MOTIVOS = [
        self::MANTENIMIENTO => 'Mantenimiento',
        self::USO_INTERNO => 'Uso interno',
    ];

    protected $fillable = [
        'habitacion_id',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
        'descripcion',
        'user_id',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    // validaciones
    static $rules = [
        'habitacion_id' => ['required', 'integer'],
        'fecha_inicio' => ['required', 'date_format:Y-m-d'],
        'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        'motivo' => ['required', 'string', 'in:mantenimiento,uso_interno'],
        'descripcion' => ['nullable', 'string'],
    ];

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    // accesores
    public function getMotivoNombreAttribute()
    {
        return self::MOTIVOS[$this->motivo] ?? '—';
    }

    // relaciones
    public function habitacion()
    {
        return $this->belongsTo(Habitacion::class, 'habitacion_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
