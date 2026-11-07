<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Huesped;
use App\Models\User;
use App\Services\SesionTokenService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\URL;
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
            'recordar' => ['sometimes', 'boolean'],
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

        $nuevo = $this->sesiones->crearToken($user, $request->boolean('recordar', true));
        $this->sesiones->registrarLogin($request, $user, $nuevo->accessToken->id);

        return response()->json([
            'token' => $nuevo->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    //Registro publico: crea un huesped, lo deja autenticado y le envia el enlace de verificacion.
    public function register(Request $request)
    {
        //El telefono se guarda solo con numeros
        $request->merge(['telefono' => preg_replace('/[^0-9]/', '', (string) $request->input('telefono')) ?: null]);

        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'string', 'max:255', 'unique:users'],
            'telefono' => ['required', 'string', 'min:8', 'max:30', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'consentimiento' => ['accepted'],
        ], [
            'telefono.unique' => 'Ese teléfono ya está registrado en otra cuenta.',
            'consentimiento.accepted' => 'Debes aceptar el aviso de privacidad para crear tu cuenta.',
        ]);

        $user = User::create([
            'name' => trim($datos['nombres'] . ' ' . $datos['apellidos']),
            'email' => $datos['email'],
            'phone' => $datos['telefono'],
            'password' => Hash::make($datos['password']),
            'estado' => 1,
        ]);
        $user->forceFill(['privacidad_aceptada_at' => now()])->save();

        // Todo usuario que se registra desde el sitio publico es un huesped.
        $user->assignRole('Huésped');
        Huesped::paraUsuario($user, [
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
        ]);

        //Si el correo falla la cuenta ya existe: el huesped puede pedir otro enlace desde Mi cuenta
        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            report($e);
        }

        $nuevo = $this->sesiones->crearToken($user, true);
        $this->sesiones->registrarLogin($request, $user, $nuevo->accessToken->id);

        return response()->json([
            'token' => $nuevo->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    //Verifica el correo desde el enlace. No exige sesion: el enlace puede abrirse en otro dispositivo.
    public function verificarCorreo(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$user || !URL::hasValidRelativeSignature($request) || !hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
            return response()->json(['message' => 'El enlace no es válido o ya venció. Pide uno nuevo desde Mi cuenta.'], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json(['message' => 'Tu correo quedó verificado. Ya puedes reservar.']);
    }

    public function reenviarVerificacion(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Tu correo ya está verificado.']);
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'No pudimos enviar el correo. Intenta de nuevo en unos minutos.'], 503);
        }

        return response()->json(['message' => 'Te enviamos un nuevo enlace. Revisa tu correo.']);
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

            // Quien tuviera la contrasena anterior deja de tener acceso.
            $this->sesiones->cerrarTodasDeUsuario($user->id);

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
