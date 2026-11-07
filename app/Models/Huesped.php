<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Huesped extends Model
{
    protected $table = 'huespedes';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'nombres',
        'apellidos',
        'tipo_documento',
        'documento',
        'email',
        'telefono',
        'nacionalidad',
        'fecha_nacimiento',
        'direccion',
        'notas',
        'estado',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    // validaciones
    static $rules = [
        'nombres' => ['required', 'string', 'max:100'],
        'apellidos' => ['required', 'string', 'max:100'],
        'tipo_documento' => ['nullable', 'string', 'max:20'],
        'documento' => ['nullable', 'string', 'max:50'],
        'email' => ['nullable', 'email', 'max:150'],
        'telefono' => ['nullable', 'string', 'max:30'],
        'nacionalidad' => ['nullable', 'string', 'max:80'],
        'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
        'direccion' => ['nullable', 'string', 'max:255'],
        'notas' => ['nullable', 'string'],
    ];

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    /**
     * Ficha de huesped de una cuenta; la crea si aun no existe.
     * No enlaza fichas existentes por correo: mientras el correo no este verificado,
     * registrarse con un correo ajeno daria acceso a las reservas de otra persona.
     * $datos completa la ficha nueva (el registro del portal manda nombres y apellidos aparte).
     */
    public static function paraUsuario(User $user, array $datos = []): self
    {
        $partes = preg_split('/\s+/', trim($user->name), 2);

        return self::firstOrCreate(['user_id' => $user->id], $datos + [
            'nombres' => $partes[0],
            'apellidos' => $partes[1] ?? '',
            'email' => $user->email,
            'telefono' => $user->phone,
        ]);
    }

    // accesores
    public function getNombreCompletoAttribute()
    {
        return trim($this->nombres . ' ' . $this->apellidos);
    }

    // relaciones
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'huesped_id', 'id');
    }
}
