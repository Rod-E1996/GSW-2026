<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TipoHabitacionImagen extends Model
{
    protected $table = 'tipo_habitacion_imagenes';
    protected $primaryKey = 'id';

    //Disco y carpeta donde se guardan las fotos
    const DISCO = 'public';
    const CARPETA = 'tipos_habitacion';

    protected $fillable = [
        'ruta',
        'nombre_original',
        'orden',
        'principal',
        'tipo_habitacion_id',
    ];

    protected $casts = [
        'principal' => 'boolean',
    ];

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    // accesores
    //URL publica de la imagen (usa asset() para respetar el dominio con que se entra al sitio)
    public function getUrlAttribute()
    {
        return asset('storage/' . $this->ruta);
    }

    //Elimina el archivo fisico del disco
    public function eliminarArchivo()
    {
        if ($this->ruta && Storage::disk(self::DISCO)->exists($this->ruta)) {
            Storage::disk(self::DISCO)->delete($this->ruta);
        }
    }

    // relaciones
    public function tipoHabitacion()
    {
        return $this->belongsTo(TipoHabitacion::class, 'tipo_habitacion_id', 'id');
    }
}
