<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }

    public function handle($request, Closure $next, ...$guards)
    {
        // Rutas de API (token Sanctum): usar el flujo estandar (token + 401 JSON),
        // no la logica de sesion/redireccion pensada para el panel Blade.
        if (in_array('sanctum', $guards, true)) {
            return parent::handle($request, $next, ...$guards);
        }

        if (!auth()->check()) {
            return redirect('/login');
        }

        if (auth()->user()->estado == 0) {
            auth()->logout();
            \Session::flush();
            return redirect('/login')->with([
                'alerta' => 'Usuario inactivo.',
                'tipo' => 'error'
            ]);
        }

        return parent::handle($request, $next, ...$guards);
    }
}
