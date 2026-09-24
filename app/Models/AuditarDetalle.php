<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditarDetalle extends Model
{
    protected $table = 'auditar_detalle';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'antes',
        'despues',
        'primary_key',
        'auditar_accion_id',
        'auditar_tabla_id'
    ];

    // relaciones
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function auditarAccion()
    {
        return $this->belongsTo(AuditarAccion::class, 'auditar_accion_id', 'id');
    }

    public function auditarTabla()
    {
        return $this->belongsTo(AuditarTabla::class, 'auditar_tabla_id', 'id');
    }

}
