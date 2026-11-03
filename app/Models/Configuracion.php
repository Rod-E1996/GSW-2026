<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';
    protected $primaryKey = 'id';

    //Tipos de parametro
    const PORCENTAJE = 'porcentaje';
    const HORAS = 'horas';

    //Reglas de validacion del valor segun su tipo
    const REGLAS_TIPO = [
        self::PORCENTAJE => ['required', 'numeric', 'min:0', 'max:100'],
        self::HORAS => ['required', 'integer', 'min:0', 'max:8760'],
    ];

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'grupo',
        'descripcion',
    ];

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    //Valor numerico de un parametro. Usa $defecto si el parametro no existe.
    public static function valor(string $clave, float $defecto = 0): float
    {
        $valor = self::where('clave', $clave)->value('valor');

        return $valor === null ? $defecto : (float) $valor;
    }
}
