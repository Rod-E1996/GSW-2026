<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comprobante extends Model
{
    protected $table = 'comprobantes';
    protected $primaryKey = 'id';

    //Estados del comprobante
    const ANULADO = 0;
    const VIGENTE = 1;
    const SUSTITUIDO = 2;

    const ESTADOS = [
        self::ANULADO => 'Anulado',
        self::VIGENTE => 'Vigente',
        self::SUSTITUIDO => 'Sustituido por una versión nueva',
    ];

    protected $fillable = [
        'reserva_id',
        'numero',
        'version',
        'qr_token',
        'ruta_pdf',
        'emitido_at',
        'verificado_at',
        'verificado_por',
        'estado',
    ];

    protected $casts = [
        'emitido_at' => 'datetime',
        'verificado_at' => 'datetime',
    ];

    public function getEstadoNombreAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? 'Desconocido';
    }

    // relaciones
    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id', 'id');
    }

    public function verificador()
    {
        return $this->belongsTo(User::class, 'verificado_por', 'id');
    }
}
