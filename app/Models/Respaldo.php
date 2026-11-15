<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Respaldo extends Model
{
    protected $table = 'respaldos';

    const MANUAL = 'manual';
    const PROGRAMADO = 'programado';

    protected $fillable = ['archivo', 'tamano', 'origen', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
