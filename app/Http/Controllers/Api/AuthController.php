<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SesionTokenService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autenticacion del portal React mediante tokens de Sanctum.
 */
class AuthController extends Controller
{
    public function __construct(private SesionTokenService $sesiones)
    {
    }

    //Login: valida credenciales y devuelve un token + los datos del usuario.
    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $datos['email'])->first();

        if (!$user || !Hash::check($datos['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        if ((int) $user->estado !== 1) {
            throw ValidationException::withMessages([
                'email' => 'Tu cuenta esta inactiva. Contacta al administrador.',
            ]);
        }

        $nuevo = $user->createToken('spa');
        $this->sesiones->registrarLogin($request, $user, $nuevo->accessToken->id);

        return response()->json([
            'token' => $nuevo->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    //Registro publico: crea un huesped y lo deja autenticado (como el flujo Blade).
    public function register(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'string', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => Hash::make($datos['password']),
            'estado' => 1,
        ]);

        // Todo usuario que se registra desde el sitio publico es un huesped.
        $user->assignRole('Huésped');

        $nuevo = $user->createToken('spa');
        $this->sesiones->registrarLogin($request, $user, $nuevo->accessToken->id);

        return response()->json([
            'token' => $nuevo->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    //Devuelve el usuario autenticado (para rehidratar la sesion en el front).
    public function me(Request $request)
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    //Logout: revoca el token actual y marca su registro de sesion como cerrado.
    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        \App\Models\SessionLog::where('session_id', (string) $token->id)
            ->where('estado', 1)
            ->update(['estado' => 0]);

        $token->delete();

        return response()->json(['message' => 'Sesion cerrada.']);
    }

    //Recuperar contrasena: envia el correo con el enlace de restablecimiento.
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Dispara el correo (User::sendPasswordResetNotification -> enlace al front).
        Password::sendResetLink($request->only('email'));

        // Respondemos siempre igual para no revelar si el correo existe o no.
        return response()->json([
            'message' => 'Si el correo esta registrado, te enviaremos un enlace para restablecer tu contrasena.',
        ]);
    }

    //Restablecer contrasena: valida el token del correo y guarda la nueva clave.
    public function resetPassword(Request $request)
    {
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $estado = Password::reset($datos, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['El enlace para restablecer no es valido o ha expirado. Solicita uno nuevo.'],
            ]);
        }

        return response()->json(['message' => 'Tu contrasena ha sido restablecida.']);
    }
}
