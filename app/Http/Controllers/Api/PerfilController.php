<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SesionTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Perfil del usuario autenticado: sus datos, su contrasena y sus sesiones.
 */
class PerfilController extends Controller
{
    public function __construct(private SesionTokenService $sesiones)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $activas = $this->sesiones->activasDeUsuario($user->id)->orderByDesc('last_used_at')->get();

        return response()->json([
            'user' => new UserResource($user),
            'idiomas' => User::IDIOMAS,
            'sesiones' => $this->sesiones->detallar($activas, $user->currentAccessToken()->id),
            'historial' => $this->sesiones->historial($user->id),
        ]);
    }

    //Actualiza los datos. Cambiar el correo pide la contrasena, porque con el se recupera la cuenta.
    public function update(Request $request)
    {
        $user = $request->user();

        //El telefono se guarda solo con numeros
        $request->merge(['phone' => preg_replace('/[^0-9]/', '', (string) $request->input('phone')) ?: null]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user->id)],
            'idioma' => ['required', 'string', Rule::in(User::IDIOMAS)],
            'current_password' => ['nullable', 'string'],
        ]);

        if ($datos['email'] !== $user->email) {
            $this->exigirContrasena($user, $datos['current_password'] ?? null);
        }

        $user->update(array_intersect_key($datos, array_flip(['name', 'email', 'phone', 'idioma'])));

        //La ficha de huesped usa estos datos para contactarlo
        $user->huesped?->update(['email' => $user->email, 'telefono' => $user->phone]);

        return response()->json([
            'message' => 'Perfil actualizado con éxito.',
            'user' => new UserResource($user),
        ]);
    }

    //Cambia la contrasena y cierra las demas sesiones: quien tuviera la anterior deja de tener acceso.
    public function password(Request $request)
    {
        $user = $request->user();

        $datos = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->exigirContrasena($user, $datos['current_password']);

        $user->password = Hash::make($datos['password']);
        $user->save();

        $cerradas = $this->sesiones->cerrarTodasDeUsuario($user->id, $user->currentAccessToken()->id);

        return response()->json([
            'message' => 'Contraseña actualizada con éxito.',
            'cerradas' => $cerradas,
        ]);
    }

    //Cierra una sesion propia. La sesion en uso se cierra con el boton de salir.
    public function cerrarSesion(Request $request, $tokenId)
    {
        $user = $request->user();

        if (!$this->sesiones->activasDeUsuario($user->id)->where('id', $tokenId)->exists()) {
            return response()->json(['message' => 'La sesión no existe o ya fue cerrada.'], 404);
        }

        if (!$this->sesiones->cerrar((int) $tokenId, $user->currentAccessToken()->id)) {
            return response()->json(['message' => 'No puedes cerrar la sesión con la que estás navegando.'], 422);
        }

        return response()->json(['message' => 'Sesión cerrada con éxito.']);
    }

    private function exigirContrasena(User $user, ?string $contrasena): void
    {
        if (!$contrasena || !Hash::check($contrasena, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }
    }
}
