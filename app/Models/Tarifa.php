<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tarifa extends Model
{
    protected $table = 'tarifas';
    protected $primaryKey = 'id';

    protected $fillable = [
        'tipo_habitacion_id',
        'temporada_id',
        'precio_noche',
        'estado',
    ];

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    // relaciones
    public function tipoHabitacion()
    {
        return $this->belongsTo(TipoHabitacion::class, 'tipo_habitacion_id', 'id');
    }

    public function temporada()
    {
        return $this->belongsTo(Temporada::class, 'temporada_id', 'id');
    }
}
