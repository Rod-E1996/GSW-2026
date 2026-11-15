<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Valoracion extends Model
{
    protected $table = 'valoraciones';

    const PENDIENTE = 'pendiente';
    const APROBADA = 'aprobada';
    const RECHAZADA = 'rechazada';

    protected $fillable = [
        'reserva_id', 'huesped_id', 'puntuacion', 'comentario', 'estado', 'respuesta', 'moderada_at', 'user_id',
    ];

    protected $casts = [
        'moderada_at' => 'datetime',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id', 'id');
    }

    public function huesped()
    {
        return $this->belongsTo(Huesped::class, 'huesped_id', 'id');
    }

    public function moderador()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
