<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Habitacion extends Model
{
    protected $table = 'habitaciones';
    protected $primaryKey = 'id';

    //Estado fisico de la habitacion. La ocupacion se deduce de las reservas.
    const DISPONIBLE = 'disponible';
    const LIMPIEZA = 'limpieza';
    const MANTENIMIENTO = 'mantenimiento';

    //Catalogo de estados: [valor => etiqueta]
    const ESTADOS = [
        self::DISPONIBLE    => 'Disponible',
        self::LIMPIEZA      => 'Limpieza',
        self::MANTENIMIENTO => 'Mantenimiento',
    ];

    protected $fillable = [
        'numero',
        'piso',
        'estado_habitacion',
        'descripcion',
        'tipo_habitacion_id',
        'estado'
    ];

    // validaciones
    static $rules = [
        'numero' => ['required', 'string', 'max:10'],
        'piso' => ['required', 'integer', 'min:0', 'max:50'],
        'tipo_habitacion_id' => ['required', 'integer', 'exists:tipos_habitacion,id'],
        'estado_habitacion' => ['required', 'string', 'in:disponible,limpieza,mantenimiento'],
        'descripcion' => ['nullable', 'string'],
    ];

    //El numero es unico en toda la tabla. Al crear se ignoran las eliminadas porque se reactivan.
    public static function rules($id = null)
    {
        $rules = self::$rules;
        $unico = Rule::unique('habitaciones', 'numero')->ignore($id);
        $rules['numero'][] = $id ? $unico : $unico->where('estado', 1);

        return $rules;
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
        return self::ESTADOS[$this->estado_habitacion] ?? 'Desconocido';
    }

    //Reservas vigentes que ocupan algun dia entre $inicio y $fin (ambos inclusive).
    //La fecha de salida no cuenta como ocupada: ese dia puede entrar otro huesped.
    public function reservasActivasEntre($inicio, $fin)
    {
        return $this->reservas()
            ->vigentes()
            ->whereDate('fecha_llegada', '<=', $fin)
            ->whereDate('fecha_salida', '>', $inicio);
    }

    //Reservas vigentes de hoy en adelante.
    public function reservasPorAtender()
    {
        return $this->reservas()
            ->whereIn('estado_reserva', [Reserva::PENDIENTE, Reserva::CONFIRMADA, Reserva::EN_ESTADIA])
            ->whereDate('fecha_salida', '>=', now()->toDateString());
    }

    // relaciones
    public function tipoHabitacion()
    {
        return $this->belongsTo(TipoHabitacion::class, 'tipo_habitacion_id', 'id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'habitacion_id', 'id');
    }

    public function bloqueos()
    {
        return $this->hasMany(BloqueoHabitacion::class, 'habitacion_id', 'id');
    }
}
