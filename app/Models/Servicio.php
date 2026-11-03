<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class Servicio extends Model
{
    protected $table = 'servicios';
    protected $primaryKey = 'id';

    //Disco y carpeta donde se guardan las imagenes
    const DISCO = 'public';
    const CARPETA = 'servicios';

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
        'categoria_servicio_id',
        'nombre',
        'nombre_en',
        'descripcion',
        'descripcion_en',
        'icono',
        'imagen',
        'precio',
        'forma_cobro',
        'requiere_fecha',
        'estado',
    ];

    protected $casts = [
        'requiere_fecha' => 'boolean',
    ];

    // validaciones
    static $rules = [
        'nombre' => ['required', 'string', 'max:100'],
        'descripcion' => ['nullable', 'string'],
        'icono' => ['nullable', 'string', 'max:50'],
        'precio' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        'forma_cobro' => ['required', 'string', 'in:por_persona_noche,por_persona,por_noche,fijo'],
        'categoria_servicio_id' => ['required', 'integer'],
        'nombre_en' => ['nullable', 'string', 'max:100'],
        'descripcion_en' => ['nullable', 'string'],
        'requiere_fecha' => ['sometimes', 'boolean'],
    ];

    //Reglas con validacion de nombre unico entre los servicios activos ($id se ignora al editar)
    public static function rules($id = null)
    {
        $rules = self::$rules;
        $rules['nombre'][] = Rule::unique('servicios', 'nombre')
            ->where('estado', 1)
            ->ignore($id);
        $rules['categoria_servicio_id'][] = Rule::exists('categorias_servicio', 'id')->where('estado', 1);

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

    //URL publica de la imagen
    public function getImagenUrlAttribute()
    {
        return $this->imagen ? asset('storage/' . $this->imagen) : null;
    }

    //Elimina el archivo fisico del disco
    public function eliminarImagen()
    {
        if ($this->imagen && Storage::disk(self::DISCO)->exists($this->imagen)) {
            Storage::disk(self::DISCO)->delete($this->imagen);
        }
    }

    // relaciones
    public function categoria()
    {
        return $this->belongsTo(CategoriaServicio::class, 'categoria_servicio_id', 'id');
    }

    public function reservas()
    {
        return $this->belongsToMany(Reserva::class, 'reserva_servicio', 'servicio_id', 'reserva_id')
            ->withPivot('cantidad', 'precio_unitario', 'subtotal', 'fecha_servicio', 'hora_servicio', 'estado_servicio', 'notas')
            ->withTimestamps();
    }
}
