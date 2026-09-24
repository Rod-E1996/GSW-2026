<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ejemplo extends Model
{
    protected $table = 'ejemplos';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'telefono',
        'fecha',
        'cantidad',
        'porcentaje',
        'select_quemado',
        'auditar_accion_id',
        'estado',
        'descripcion'
    ];

    // validaciones
    static $rules = [
        'nombre' => ['required', 'string', 'max:200'],
        'telefono' => ['required', 'string', 'min:9', 'max:9'],
        'fecha' => ['required', 'date'],
        'cantidad' => ['required', 'numeric'],
        'porcentaje' => ['required', 'integer'],
        'select_quemado' => ['required', 'integer'],
        'auditar_accion_id' => ['required', 'integer'],
        'descripcion' => ['nullable', 'string'],
    ];

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    // relaciones
    public function auditarAccion(){
        return $this->belongsTo(AuditarAccion::class, 'auditar_accion_id', 'id');
    }
}
