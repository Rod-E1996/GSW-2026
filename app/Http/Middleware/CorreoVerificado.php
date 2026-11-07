<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Exige el correo verificado. Responde en espanol y con un codigo que el portal reconoce.
 */
class CorreoVerificado
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()?->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Verifica tu correo para poder reservar. Te enviamos un enlace al registrarte.',
                'codigo' => 'correo_sin_verificar',
            ], 403);
        }

        return $next($request);
    }
}
