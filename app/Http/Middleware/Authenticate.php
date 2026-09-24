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
