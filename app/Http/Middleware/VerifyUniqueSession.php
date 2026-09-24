<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class VerifyUniqueSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $userId = Auth::id();
            $sessionId = Session::getId();

            //*******************>>>>>>  COMENTAR ESTO QUITAR LA SESION UNICA  <<<<<<*******************/
            $otherSessions = \App\Models\Session::where('user_id', $userId)->where('id', '!=', $sessionId)->get();  // Buscar otras sesiones del mismo usuario

            foreach ($otherSessions as $session) {// Eliminar las sesiones más antiguas del usuario
                Session::getHandler()->destroy($session->id);
                $session->delete();
            }
            //*******************>>>>>>  COMENTAR ESTO QUITAR LA SESION UNICA  <<<<<<*******************/

            // Actualizar o crear la sesión actual
            $existingSession = \App\Models\Session::where('user_id', $userId)
                ->where('id', $sessionId)
                ->first();

            if ($existingSession) {
                $existingSession->ip_address = $request->ip();
                $existingSession->user_agent = $request->userAgent();
                $existingSession->last_activity = time();
                $existingSession->save();
            } else {
                \App\Models\Session::create([
                    'user_id' => $userId,
                    'id' => $sessionId,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'payload' => '',
                    'last_activity' => time(),
                ]);
            }
        }

        return $next($request);
    }
}
