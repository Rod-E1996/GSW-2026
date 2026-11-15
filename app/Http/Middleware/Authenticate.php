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
        //Laravel ya no tiene pantalla de inicio de sesion: se manda al login del portal
        if (! $request->expectsJson()) {
            return rtrim(config('app.frontend_url'), '/') . '/login';
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
