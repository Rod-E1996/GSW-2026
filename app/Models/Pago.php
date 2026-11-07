<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pagos';
    protected $primaryKey = 'id';

    //Estados del pago
    const PENDIENTE = 1;
    const APROBADO = 2;
    const RECHAZADO = 3;

    const ESTADOS = [
        self::PENDIENTE => 'Pendiente',
        self::APROBADO => 'Aprobado',
        self::RECHAZADO => 'Rechazado',
    ];

    //Tipos de pago
    const ANTICIPO = 'anticipo';
    const SALDO = 'saldo';

    protected $fillable = [
        'reserva_id',
        'tipo_pago',
        'metodo_pago',
        'pasarela',
        'referencia_externa',
        'monto',
        'estado_pago',
        'respuesta_pasarela',
        'pagado_at',
        'user_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'respuesta_pasarela' => 'array',
        'pagado_at' => 'datetime',
    ];

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    public function getEstadoNombreAttribute(): string
    {
        return self::ESTADOS[$this->estado_pago] ?? 'Desconocido';
    }

    public function scopeAprobados($query)
    {
        return $query->where('estado_pago', self::APROBADO);
    }

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
