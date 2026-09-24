<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionLog extends Model
{
    protected $table = 'sessions_logs';
    protected $primaryKey = 'id';

    protected $fillable = [
        'session_id',
        'user_id',
        'ip_address',
        'device',
        'platform',
        'browser',
        'device_type',
        'device_model',
        'estado',
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
