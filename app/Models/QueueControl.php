<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueControl extends Model
{
    protected $table = 'queue_control';
    protected $primaryKey = 'id';

    protected $fillable = [
        'titulo',
        'total_procesos',
        'progreso',
        'pendiente',
        'fecha_inicio',
        'fecha_fin',
        'user_id',
        'estado',
    ];

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        //'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    // relaciones
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
