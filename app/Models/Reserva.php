<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    protected $table = 'reservas';
    protected $primaryKey = 'id';

    //Estados de la reserva
    const PENDIENTE = 1;
    const CONFIRMADA = 2;
    const EN_ESTADIA = 3;
    const FINALIZADA = 4;
    const CANCELADA = 5;

    //Canal de origen de la reserva
    const CANAL_WEB = 'web';
    const CANAL_TELEFONO = 'telefono';
    const CANAL_PRESENCIAL = 'presencial';

    //Estadia mas larga que se acepta en linea
    const MAX_NOCHES = 60;

    //Sin 0, O, 1 ni I: el codigo se dicta y se copia a mano
    const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    //Catalogo de estados: [valor => [etiqueta, color bootstrap]]
    const ESTADOS = [
        self::PENDIENTE  => ['nombre' => 'Pendiente',   'color' => 'warning'],
        self::CONFIRMADA => ['nombre' => 'Confirmada',  'color' => 'success'],
        self::EN_ESTADIA => ['nombre' => 'En estadía',  'color' => 'primary'],
        self::FINALIZADA => ['nombre' => 'Finalizada',  'color' => 'secondary'],
        self::CANCELADA  => ['nombre' => 'Cancelada',   'color' => 'danger'],
    ];

    //Comentario para la siguiente entrada de reserva_historial (por ejemplo, el motivo de una cancelacion)
    public ?string $comentarioHistorial = null;

    protected $fillable = [
        'codigo',
        'huesped_id',
        'tipo_habitacion_id',
        'habitacion_id',
        'fecha_llegada',
        'fecha_salida',
        'adultos',
        'ninos',
        'hora_estimada_llegada',
        'canal',
        'estado_reserva',
        'subtotal_alojamiento',
        'subtotal_servicios',
        'descuento',
        'impuesto',
        'total',
        'anticipo_requerido',
        'notas',
        'check_in_at',
        'check_out_at',
        'cancelada_at',
        'motivo_cancelacion',
        'user_id',
        'estado',
    ];

    protected $casts = [
        'fecha_llegada' => 'date',
        'fecha_salida' => 'date',
        'subtotal_alojamiento' => 'decimal:2',
        'subtotal_servicios' => 'decimal:2',
        'descuento' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'total' => 'decimal:2',
        'anticipo_requerido' => 'decimal:2',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'cancelada_at' => 'datetime',
    ];

    // validaciones (el huesped y las fechas; la disponibilidad se valida en el servicio)
    static $rules = [
        'huesped_id' => ['required', 'integer', 'exists:huespedes,id'],
        'tipo_habitacion_id' => ['required', 'integer', 'exists:tipos_habitacion,id'],
        'fecha_llegada' => ['required', 'date', 'after_or_equal:today'],
        'fecha_salida' => ['required', 'date', 'after:fecha_llegada'],
        'adultos' => ['required', 'integer', 'min:1', 'max:20'],
        'ninos' => ['nullable', 'integer', 'min:0', 'max:20'],
        'hora_estimada_llegada' => ['nullable', 'date_format:H:i'],
        'canal' => ['nullable', 'string', 'in:web,telefono,presencial'],
        'notas' => ['nullable', 'string'],
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

    //Toda reserva nueva y todo cambio de estado quedan en reserva_historial
    protected static function booted()
    {
        static::created(function (Reserva $reserva) {
            $reserva->registrarHistorial(null);
        });

        static::updated(function (Reserva $reserva) {
            if ($reserva->wasChanged('estado_reserva')) {
                $reserva->registrarHistorial($reserva->getOriginal('estado_reserva'));
            }
        });
    }

    private function registrarHistorial($estadoAnterior): void
    {
        $this->historial()->create([
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $this->estado_reserva,
            'comentario' => $this->comentarioHistorial,
            'user_id' => auth()->id(),
        ]);

        $this->comentarioHistorial = null;
    }

    //Codigo de confirmacion aleatorio, para que no se pueda adivinar el de otra reserva
    public static function generarCodigo(): string
    {
        do {
            $codigo = 'HL-';
            for ($i = 0; $i < 8; $i++) {
                $codigo .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
            }
        } while (self::where('codigo', $codigo)->exists());

        return $codigo;
    }

    //Reservas que ocupan inventario. Una pendiente vencida deja de retener la habitacion.
    public function scopeVigentes($query)
    {
        $limite = now()->subHours((int) Configuracion::valor('reserva_pendiente_horas', 24));

        return $query->where('estado', 1)->where(function ($q) use ($limite) {
            $q->whereIn('estado_reserva', [self::CONFIRMADA, self::EN_ESTADIA])
                ->orWhere(function ($p) use ($limite) {
                    $p->where('estado_reserva', self::PENDIENTE)->where('created_at', '>', $limite);
                });
        });
    }

    // accesores
    public function getEstadoNombreAttribute()
    {
        return self::ESTADOS[$this->estado_reserva]['nombre'] ?? 'Desconocido';
    }

    public function getEstadoColorAttribute()
    {
        return self::ESTADOS[$this->estado_reserva]['color'] ?? 'dark';
    }

    //Numero de noches de la estadia
    public function getNochesTotalAttribute()
    {
        if (!$this->fecha_llegada || !$this->fecha_salida) {
            return 0;
        }
        return $this->fecha_llegada->diffInDays($this->fecha_salida);
    }

    // relaciones
    public function tipoHabitacion()
    {
        return $this->belongsTo(TipoHabitacion::class, 'tipo_habitacion_id', 'id');
    }

    public function habitacion()
    {
        return $this->belongsTo(Habitacion::class, 'habitacion_id', 'id');
    }

    public function huesped()
    {
        return $this->belongsTo(Huesped::class, 'huesped_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function noches()
    {
        return $this->hasMany(ReservaNoche::class, 'reserva_id', 'id')->orderBy('fecha');
    }

    public function historial()
    {
        return $this->hasMany(ReservaHistorial::class, 'reserva_id', 'id')->orderBy('id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'reserva_id', 'id')->orderBy('id');
    }

    public function comprobantes()
    {
        return $this->hasMany(Comprobante::class, 'reserva_id', 'id')->orderBy('version');
    }

    //El comprobante que vale hoy: al emitir una version nueva la anterior queda sustituida
    public function comprobanteVigente()
    {
        return $this->hasOne(Comprobante::class, 'reserva_id', 'id')
            ->where('estado', Comprobante::VIGENTE)
            ->orderByDesc('version');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'reserva_id', 'id')->orderBy('id');
    }

    //Suma de los pagos aprobados
    public function montoPagado(): float
    {
        return round((float) $this->pagos()->aprobados()->sum('monto'), 2);
    }

    public function servicios()
    {
        return $this->belongsToMany(Servicio::class, 'reserva_servicio', 'reserva_id', 'servicio_id')
            ->withPivot('cantidad', 'precio_unitario', 'subtotal', 'fecha_servicio', 'hora_servicio', 'estado_servicio', 'notas')
            ->withTimestamps();
    }
}
