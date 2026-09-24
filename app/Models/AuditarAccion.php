<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditarAccion extends Model
{
    protected $table = 'auditar_acciones';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre'
    ];

    // relaciones
    public function auditarDetalle(){
        return $this->hasMany(AuditarDetalle::class, 'auditar_accion_id', 'id');
    }

    public function ejemplo(){
        return $this->hasMany(Ejemplo::class, 'auditar_accion_id', 'id');
    }
}
