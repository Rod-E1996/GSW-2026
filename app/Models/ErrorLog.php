<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $table = 'error_logs';
    protected $primaryKey = 'id';

    protected $fillable = [
        'controller',
        'mensaje',
        'parametros',
        'user_id',
        'estado'
    ];

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    // relaciones
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
