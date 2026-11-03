<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CategoriaServicio extends Model
{
    protected $table = 'categorias_servicio';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'nombre_en',
        'icono',
        'orden',
        'estado',
    ];

    // validaciones
    static $rules = [
        'nombre' => ['required', 'string', 'max:80'],
        'nombre_en' => ['nullable', 'string', 'max:80'],
        'icono' => ['nullable', 'string', 'max:50'],
        'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
    ];

    //Reglas con validacion de nombre unico entre las categorias activas ($id se ignora al editar)
    public static function rules($id = null)
    {
        $rules = self::$rules;
        $rules['nombre'][] = Rule::unique('categorias_servicio', 'nombre')
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

    // relaciones
    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'categoria_servicio_id', 'id');
    }
}
