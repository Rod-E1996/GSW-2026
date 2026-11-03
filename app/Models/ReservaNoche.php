<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservaNoche extends Model
{
    protected $table = 'reserva_noches';
    protected $primaryKey = 'id';

    protected $fillable = [
        'reserva_id',
        'habitacion_id',
        'fecha',
        'temporada_id',
        'precio_noche',
    ];

    protected $casts = [
        'fecha' => 'date',
        'precio_noche' => 'decimal:2',
    ];

    // relaciones
    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id', 'id');
    }

    public function habitacion()
    {
        return $this->belongsTo(Habitacion::class, 'habitacion_id', 'id');
    }

    public function temporada()
    {
        return $this->belongsTo(Temporada::class, 'temporada_id', 'id');
    }
}
