<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Servicio extends Model
{
    protected $table = 'servicios';
    protected $primaryKey = 'id';

    //Formas de cobro de un servicio
    const POR_PERSONA_NOCHE = 'por_persona_noche';
    const POR_PERSONA = 'por_persona';
    const POR_NOCHE = 'por_noche';
    const FIJO = 'fijo';

    //Catalogo: [valor => etiqueta]
    const FORMAS_COBRO = [
        self::POR_PERSONA_NOCHE => 'Por persona y noche',
        self::POR_PERSONA => 'Por persona',
        self::POR_NOCHE => 'Por noche',
        self::FIJO => 'Precio fijo',
    ];

    protected $fillable = [
        'nombre',
        'descripcion',
        'icono',
        'precio',
        'forma_cobro',
        'estado',
    ];

    // validaciones
    static $rules = [
        'nombre' => ['required', 'string', 'max:100'],
        'descripcion' => ['nullable', 'string'],
        'icono' => ['nullable', 'string', 'max:50'],
        'precio' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        'forma_cobro' => ['required', 'string', 'in:por_persona_noche,por_persona,por_noche,fijo'],
    ];

    //Reglas con validacion de nombre unico entre los servicios activos ($id se ignora al editar)
    public static function rules($id = null)
    {
        $rules = self::$rules;
        $rules['nombre'][] = Rule::unique('servicios', 'nombre')
            ->where('estado', 1)
            ->ignore($id);

        return $rules;
    }

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    // accesores
    public function getFormaCobroNombreAttribute()
    {
        return self::FORMAS_COBRO[$this->forma_cobro] ?? 'Precio fijo';
    }

    // relaciones
    public function reservas()
    {
        return $this->belongsToMany(Reserva::class, 'reserva_servicio', 'servicio_id', 'reserva_id')
            ->withPivot('cantidad', 'precio_unitario', 'subtotal')
            ->withTimestamps();
    }
}
