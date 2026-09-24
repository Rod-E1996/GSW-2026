<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditarTabla extends Model
{
    protected $table = 'auditar_tablas';
    protected $primaryKey = 'id';

    protected $fillable = [
        'tabla',
        'modelo',
    ];

    // relaciones
    public function auditarDetalle(){
        return $this->hasMany(AuditarDetalle::class, 'auditar_tabla_id', 'id');
    }

}
