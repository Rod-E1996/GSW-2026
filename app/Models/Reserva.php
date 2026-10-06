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
    const EN_CASA = 3;
    const SALIDA = 4;
    const CANCELADA = 5;

    //Catalogo de estados: [valor => [etiqueta, color bootstrap]]
    const ESTADOS = [
        self::PENDIENTE  => ['nombre' => 'Pendiente',   'color' => 'warning'],
        self::CONFIRMADA => ['nombre' => 'Confirmada',  'color' => 'success'],
        self::EN_CASA    => ['nombre' => 'En casa',     'color' => 'primary'],
        self::SALIDA     => ['nombre' => 'Salida',      'color' => 'secondary'],
        self::CANCELADA  => ['nombre' => 'Cancelada',   'color' => 'danger'],
    ];

    protected $fillable = [
        'codigo',
        'tipo_habitacion_id',
        'habitacion_id',
        'fecha_llegada',
        'fecha_salida',
        'adultos',
        'ninos',
        'huesped_nombre',
        'huesped_documento',
        'huesped_email',
        'huesped_telefono',
        'hora_estimada_llegada',
        'estado_reserva',
        'total',
        'user_id',
        'estado',
    ];

    protected $casts = [
        'fecha_llegada' => 'date',
        'fecha_salida' => 'date',
        'total' => 'decimal:2',
    ];

    // validaciones (los datos del huesped y las fechas; la disponibilidad se valida en el servicio)
    static $rules = [
        'tipo_habitacion_id' => ['required', 'integer', 'exists:tipos_habitacion,id'],
        'fecha_llegada' => ['required', 'date', 'after_or_equal:today'],
        'fecha_salida' => ['required', 'date', 'after:fecha_llegada'],
        'adultos' => ['required', 'integer', 'min:1', 'max:20'],
        'ninos' => ['nullable', 'integer', 'min:0', 'max:20'],
        'huesped_nombre' => ['required', 'string', 'max:150'],
        'huesped_documento' => ['nullable', 'string', 'max:50'],
        'huesped_email' => ['required', 'email', 'max:150'],
        'huesped_telefono' => ['nullable', 'string', 'max:30'],
        'hora_estimada_llegada' => ['nullable', 'date_format:H:i'],
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

    // accesores
    public function getEstadoNombreAttribute()
    {
        return self::ESTADOS[$this->estado_reserva]['nombre'] ?? 'Desconocido';
    }

    public function getEstadoColorAttribute()
    {
        return self::ESTADOS[$this->estado_reserva]['color'] ?? 'dark';
    }

    //Numero de noches de la estancia
    public function getNochesAttribute()
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function servicios()
    {
        return $this->belongsToMany(Servicio::class, 'reserva_servicio', 'reserva_id', 'servicio_id')
            ->withPivot('cantidad', 'precio_unitario', 'subtotal')
            ->withTimestamps();
    }
}
