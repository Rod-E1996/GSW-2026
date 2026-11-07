<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Validation\Rule;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles;

    //Idiomas del portal bilingue
    const IDIOMAS = ['es', 'en'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'idioma',
        'estado',
        'login_notificacion'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'privacidad_aceptada_at' => 'datetime',
    ];

    // validaciones
    static $rules = [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'string', 'max:255', 'unique:users'],
        'password' => ['required', 'string', 'confirmed'],
        'id_rol' => ['required', 'array'],
    ];

    static function validateUpdate($id){
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'string', 'max:255', Rule::unique('users')->ignore($id) ],
            'password' => ['nullable', 'string', 'confirmed'],
            'id_rol' => ['required', 'array'],
        ];
    }

    //Eventos auditoria
    protected $dispatchesEvents = [
        'created' => \App\Events\SaveEvent::class,
        'updating' => \App\Events\UpdateEvent::class,
        'deleting' => \App\Events\DeleteEvent::class,
    ];

    //Relaciones
    public function auditarDetalle()
    {
        return $this->hasMany(AuditarDetalle::class, 'user_id', 'id');
    }

    public function huesped()
    {
        return $this->hasOne(Huesped::class, 'user_id', 'id');
    }

    public function errorLog()
    {
        return $this->hasMany(ErrorLog::class, 'user_id', 'id');
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\CustomResetPasswordNotification($token));
    }

    //El enlace abre el portal React; tambien lo usa el registro por Blade
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerificarCorreoNotification());
    }

}
