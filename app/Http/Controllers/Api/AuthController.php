<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticacion del portal React mediante tokens de Sanctum.
 */
class AuthController extends Controller
{
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

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    //Devuelve el usuario autenticado (para rehidratar la sesion en el front).
    public function me(Request $request)
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    //Logout: revoca el token actual.
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesion cerrada.']);
    }
}
