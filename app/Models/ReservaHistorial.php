<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservaHistorial extends Model
{
    protected $table = 'reserva_historial';
    protected $primaryKey = 'id';

    //El historial solo se agrega, nunca se modifica
    const UPDATED_AT = null;

    protected $fillable = [
        'reserva_id',
        'estado_anterior',
        'estado_nuevo',
        'comentario',
        'user_id',
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
