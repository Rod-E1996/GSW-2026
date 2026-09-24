<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Session;
use App\Models\User;
use App\Services\SessionesService;

/**
 * Pantalla global de sesiones: muestra las sesiones abiertas de todos los
 * usuarios del sistema y permite cerrarlas una por una.
 */
class SessionesController extends Controller
{
    public function __construct(private SessionesService $sessiones)
    {
    }

    //Funcion para mostrar todas las sesiones activas del sistema
    public function index(Request $request)
    {
        $keyword     = trim((string) $request->get('search'));
        $dispositivo = $request->get('dispositivo');
        $perPage     = 15;

        $query = $this->sessiones->activas()->with('user');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('ip_address', 'LIKE', "%$keyword%")
                  ->orWhereHas('user', function ($u) use ($keyword) {
                      $u->where('name', 'LIKE', "%$keyword%")
                        ->orWhere('email', 'LIKE', "%$keyword%");
                  });
            });
        }

        if (in_array($dispositivo, ['Desktop', 'Mobile', 'Tablet'], true)) {
            $query->whereIn('id', \App\Models\SessionLog::where('device_type', $dispositivo)->select('session_id'));
        }

        $paginado = (clone $query)->orderByDesc('last_activity')->paginate($perPage)->withQueryString();

        $activas = $this->sessiones->activas();

        $data['sessiones']         = $this->sessiones->detallar($paginado->getCollection());
        $data['paginado']          = $paginado;
        $data['total_activas']     = (clone $activas)->count();
        $data['usuarios_conectados'] = (clone $activas)->distinct('user_id')->count('user_id');
        $data['usuarios_total']    = User::where('estado', 1)->count();
        $data['dispositivos']      = $this->sessiones->conteoPorDispositivo($activas);
        $data['lifetime']          = $this->sessiones->lifetime();
        $data['session_actual']    = $this->sessiones->sessionActualId();

        return view('admin.sessiones.index', $data);
    }

    //Funcion para cerrar una sesion puntual (solo de forma individual)
    public function cerrar($session_id)
    {
        $session = Session::with('user')->find($session_id);

        if (!$session) {
            return back()->with([
                'alerta' => 'La sesión ya no existe o ya fue cerrada.',
                'tipo'   => 'warning',
            ]);
        }

        if ($session->id === $this->sessiones->sessionActualId()) {
            return back()->with([
                'alerta' => 'No puedes cerrar la sesión con la que estás navegando. Usa "Cerrar sesión" en tu perfil.',
                'tipo'   => 'warning',
            ]);
        }

        $nombre = $session->user->name ?? 'usuario';

        if ($this->sessiones->cerrar($session)) {
            return back()->with('alerta', "Sesión de $nombre cerrada con éxito.");
        }

        return back()->with([
            'alerta' => 'Ocurrió un error al cerrar la sesión.',
            'tipo'   => 'error',
        ]);
    }
}
