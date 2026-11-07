<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';
    protected $primaryKey = 'id';

    //Estados del envio
    const PENDIENTE = 1;
    const ENVIADO = 2;
    const FALLIDO = 3;

    //Tipos de aviso de la reserva
    const REGISTRO = 'registro';
    const CONFIRMACION = 'confirmacion';
    const MODIFICACION = 'modificacion';
    const CANCELACION = 'cancelacion';

    const CANAL_CORREO = 'correo';

    protected $fillable = [
        'reserva_id',
        'user_id',
        'tipo',
        'canal',
        'destinatario',
        'asunto',
        'estado_envio',
        'intentos',
        'error',
        'enviado_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
    ];

    // relaciones
    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
