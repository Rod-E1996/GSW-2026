<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TipoHabitacion extends Model
{
    protected $table = 'tipos_habitacion';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'nombre_en',
        'slug',
        'capacidad',
        'precio_base',
        'descripcion',
        'descripcion_en',
        'orden',
        'estado'
    ];

    // validaciones
    static $rules = [
        'nombre' => ['required', 'string', 'max:100'],
        'capacidad' => ['required', 'integer', 'min:1', 'max:20'],
        'precio_base' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        'descripcion' => ['nullable', 'string'],
        'nombre_en' => ['nullable', 'string', 'max:100'],
        'descripcion_en' => ['nullable', 'string'],
        'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
    ];

    //Reglas con validacion de nombre unico entre los tipos activos ($id se ignora al editar)
    public static function rules($id = null)
    {
        $rules = self::$rules;
        $rules['nombre'][] = Rule::unique('tipos_habitacion', 'nombre')
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

    //El slug se genera una sola vez: renombrar el tipo no cambia su URL
    protected static function booted()
    {
        static::creating(function (TipoHabitacion $tipo) {
            $tipo->slug = $tipo->slug ?: self::generarSlug($tipo->nombre);
        });
    }

    //Slug unico en toda la tabla, incluidos los tipos eliminados
    public static function generarSlug(string $nombre): string
    {
        $base = Str::limit(Str::slug($nombre) ?: 'tipo', 110, '');
        $slug = $base;

        for ($i = 2; self::where('slug', $slug)->exists(); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    // relaciones
    public function habitaciones()
    {
        return $this->hasMany(Habitacion::class, 'tipo_habitacion_id', 'id');
    }

    public function tarifas()
    {
        return $this->hasMany(Tarifa::class, 'tipo_habitacion_id', 'id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'tipo_habitacion_id', 'id');
    }

    public function imagenes()
    {
        return $this->hasMany(TipoHabitacionImagen::class, 'tipo_habitacion_id', 'id')->orderBy('orden')->orderBy('id');
    }

    public function imagenPrincipal()
    {
        return $this->hasOne(TipoHabitacionImagen::class, 'tipo_habitacion_id', 'id')->where('principal', true);
    }
}
