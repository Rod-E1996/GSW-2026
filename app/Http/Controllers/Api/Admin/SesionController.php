<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SessionLog;
use App\Models\User;
use App\Services\SesionTokenService;
use Illuminate\Http\Request;

/**
 * Sesiones del SPA (tokens de Sanctum): vista global del sistema y por usuario.
 */
class SesionController extends Controller
{
    public function __construct(private SesionTokenService $sesiones)
    {
    }

    //Vista global: todas las sesiones (tokens) activas del sistema.
    public function index(Request $request)
    {
        $query = $this->sesiones->activas()->with('tokenable');

        if ($keyword = $request->get('search')) {
            $query->whereHas('tokenable', function ($u) use ($keyword) {
                $u->where('name', 'LIKE', "%$keyword%")
                    ->orWhere('email', 'LIKE', "%$keyword%");
            });
        }

        $paginado = $query->orderByDesc('last_used_at')->orderByDesc('id')->paginate(15);
        $tokenActual = $request->user()->currentAccessToken()->id;

        return response()->json([
            'data' => $this->sesiones->detallar(collect($paginado->items()), $tokenActual),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'total' => $paginado->total(),
                'per_page' => $paginado->perPage(),
            ],
            'stats' => [
                'total_activas' => $this->sesiones->activas()->count(),
                'usuarios_conectados' => $this->sesiones->activas()->distinct('tokenable_id')->count('tokenable_id'),
            ],
        ]);
    }

    //Cierra una sesion puntual del sistema.
    public function cerrar(Request $request, $tokenId)
    {
        $tokenActual = $request->user()->currentAccessToken()->id;

        if ((int) $tokenId === (int) $tokenActual) {
            return response()->json([
                'message' => 'No puedes cerrar la sesión con la que estás navegando.',
            ], 422);
        }

        if (!$this->sesiones->cerrar((int) $tokenId, $tokenActual)) {
            return response()->json(['message' => 'La sesión ya no existe o ya fue cerrada.'], 404);
        }

        return response()->json(['message' => 'Sesión cerrada con éxito.']);
    }

    //Sesiones activas e historial de inicios de sesion de un usuario.
    public function deUsuario(Request $request, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'El usuario no existe.'], 404);
        }

        $tokenActual = $request->user()->currentAccessToken()->id;
        $activas = $this->sesiones->activasDeUsuario($user->id)->orderByDesc('last_used_at')->get();

        $historial = SessionLog::where('user_id', $user->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (SessionLog $log) => [
                'id' => $log->id,
                'ip_address' => $log->ip_address ?? 'Desconocida',
                'platform' => $log->platform ?? 'Desconocido',
                'browser' => $log->browser ?? 'Desconocido',
                'device_type_es' => $this->sesiones->tipoDispositivo($log->device_type),
                'estado' => (int) $log->estado,
                'fecha' => $log->created_at,
            ]);

        return response()->json([
            'usuario' => ['id' => $user->id, 'name' => $user->name],
            'sesiones' => $this->sesiones->detallar($activas, $tokenActual),
            'total_activas' => $activas->count(),
            'historial' => $historial,
        ]);
    }

    //Cierra una sesion puntual de un usuario.
    public function cerrarDeUsuario(Request $request, $userId, $tokenId)
    {
        $pertenece = $this->sesiones->activasDeUsuario((int) $userId)->where('id', $tokenId)->exists();

        if (!$pertenece) {
            return response()->json(['message' => 'La sesión no existe o no pertenece al usuario.'], 404);
        }

        $tokenActual = $request->user()->currentAccessToken()->id;

        if (!$this->sesiones->cerrar((int) $tokenId, $tokenActual)) {
            return response()->json(['message' => 'No se pudo cerrar la sesión (¿es la actual?).'], 422);
        }

        return response()->json(['message' => 'Sesión cerrada con éxito.']);
    }

    //Cierra todas las sesiones de un usuario (excepto la actual de quien lo solicita).
    public function cerrarTodasDeUsuario(Request $request, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'El usuario no existe.'], 404);
        }

        $tokenActual = $request->user()->currentAccessToken()->id;
        $cerradas = $this->sesiones->cerrarTodasDeUsuario($user->id, $tokenActual);

        return response()->json([
            'message' => $cerradas === 0
                ? 'El usuario no tiene sesiones abiertas que se puedan cerrar.'
                : ($cerradas === 1 ? 'Se cerró 1 sesión.' : "Se cerraron $cerradas sesiones."),
            'cerradas' => $cerradas,
        ]);
    }
}
