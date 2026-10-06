<?php

namespace App\Services;

use App\Jobs\SendEmailNuevoDispositivo;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Jenssegers\Agent\Agent;
use Laravel\Sanctum\PersonalAccessToken;
use Mobile_Detect;

/**
 * Manejo de "sesiones" en el mundo del SPA: cada sesion es un token de Sanctum.
 * La metadata del dispositivo/IP se guarda en sessions_logs, usando el id del
 * token como session_id. Reemplaza al SessionesService (basado en sesiones web).
 */
class SesionTokenService
{
    //Registra el inicio de sesion: crea el SessionLog ligado al token y avisa si es un dispositivo nuevo.
    public function registrarLogin(Request $request, User $user, int $tokenId): SessionLog
    {
        $info = $this->detectar($request);

        $primerInicio = SessionLog::where('user_id', $user->id)->exists();
        $dispositivoConocido = SessionLog::where('user_id', $user->id)
            ->where('device_model', $info['device_model'])
            ->exists();

        if ($user->login_notificacion == 1 && $primerInicio && !$dispositivoConocido) {
            SendEmailNuevoDispositivo::dispatch(
                $user,
                $info['ip_address'],
                $info['platform'],
                $info['browser'],
                $info['device_type']
            );
        }

        return SessionLog::create([
            'session_id' => (string) $tokenId,
            'user_id' => $user->id,
            'ip_address' => $info['ip_address'],
            'device' => $info['device'],
            'platform' => $info['platform'],
            'browser' => $info['browser'],
            'device_type' => $info['device_type'],
            'device_model' => $info['device_model'],
            'estado' => 1,
        ]);
    }

    //Query base: tokens vigentes (no expirados) de usuarios.
    public function activas(): Builder
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function activasDeUsuario(int $userId): Builder
    {
        return $this->activas()->where('tokenable_id', $userId);
    }

    //Conteo de sesiones activas por usuario (para la lista de usuarios).
    public function conteoActivasPorUsuario(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return $this->activas()
            ->whereIn('tokenable_id', $userIds)
            ->selectRaw('tokenable_id, COUNT(*) as total')
            ->groupBy('tokenable_id')
            ->pluck('total', 'tokenable_id')
            ->toArray();
    }

    /**
     * Convierte una coleccion de tokens en objetos para la vista, cruzando
     * cada uno con su registro de dispositivo en sessions_logs.
     */
    public function detallar(Collection $tokens, ?int $tokenActualId = null): Collection
    {
        $logs = SessionLog::query()
            ->whereIn('session_id', $tokens->pluck('id')->map(fn ($id) => (string) $id))
            ->get()
            ->keyBy('session_id');

        return $tokens->map(function (PersonalAccessToken $token) use ($logs, $tokenActualId) {
            $log = $logs->get((string) $token->id);

            return [
                'id' => $token->id,
                'usuario' => $token->tokenable->name ?? null,
                'ip_address' => $log->ip_address ?? 'Desconocida',
                'platform' => $log->platform ?? 'Desconocido',
                'browser' => $log->browser ?? 'Desconocido',
                'device_type' => $log->device_type ?? 'Unknown',
                'device_type_es' => $this->tipoDispositivo($log->device_type ?? null),
                'device_model' => $log->device_model ?? null,
                'inicio' => $log->created_at ?? $token->created_at,
                'ultima_actividad' => $token->last_used_at,
                'es_actual' => $tokenActualId !== null && $token->id === $tokenActualId,
            ];
        });
    }

    //Cierra (revoca) un token. Nunca cierra el token actual del que lo solicita.
    public function cerrar(int $tokenId, ?int $tokenActualId = null): bool
    {
        if ($tokenActualId !== null && $tokenId === $tokenActualId) {
            return false;
        }

        $token = PersonalAccessToken::find($tokenId);
        if (!$token) {
            return false;
        }

        SessionLog::where('session_id', (string) $tokenId)
            ->where('estado', 1)
            ->update(['estado' => 0]);

        return (bool) $token->delete();
    }

    //Cierra todas las sesiones de un usuario (excepto la actual de quien lo solicita).
    public function cerrarTodasDeUsuario(int $userId, ?int $tokenActualId = null): int
    {
        $cerradas = 0;

        $this->activasDeUsuario($userId)->get()->each(function (PersonalAccessToken $token) use (&$cerradas, $tokenActualId) {
            if ($this->cerrar($token->id, $tokenActualId)) {
                $cerradas++;
            }
        });

        return $cerradas;
    }

    public function tipoDispositivo(?string $deviceType): string
    {
        return match ($deviceType) {
            'Desktop' => 'Escritorio',
            'Mobile' => 'Móvil',
            'Tablet' => 'Tableta',
            default => 'Desconocido',
        };
    }

    //Detecta dispositivo/navegador/plataforma/IP (portado del LoginController Blade).
    private function detectar(Request $request): array
    {
        $agent = new Agent();
        $detect = new Mobile_Detect();

        $platform = $agent->platform();
        $browser = $agent->browser();

        if ($detect->isMobile() && !$detect->isTablet()) {
            $deviceType = 'Mobile';
            $deviceModel = $detect->isiPhone() ? 'iPhone' : ($detect->isAndroidOS() ? 'Android' : 'Mobile');
        } elseif ($detect->isTablet()) {
            $deviceType = 'Tablet';
            $deviceModel = $detect->isiPad() ? 'iPad' : 'Tablet';
        } elseif ($agent->isDesktop()) {
            $deviceType = 'Desktop';
            $deviceModel = $this->modeloEscritorio($agent->getUserAgent());
        } else {
            $deviceType = 'Unknown';
            $deviceModel = 'Unknown';
        }

        return [
            'ip_address' => $this->ipReal($request),
            'device' => $agent->device() ?: 'Unknown',
            'platform' => trim($platform . ' ' . $agent->version($platform)),
            'browser' => trim($browser . ' ' . $agent->version($browser)),
            'device_type' => $deviceType,
            'device_model' => $deviceModel,
        ];
    }

    private function ipReal(Request $request): string
    {
        if ($request->headers->has('CF-Connecting-IP')) {
            return $request->headers->get('CF-Connecting-IP');
        }

        return $request->ip() ?? 'Desconocida';
    }

    private function modeloEscritorio(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;

        if (preg_match('/\bWindows NT\b.*\b(\d+\.\d+)\b/', $userAgent, $m)) {
            return 'Windows ' . $m[1];
        }
        if (preg_match('/\bMac OS X\b.*\b(\d+[_.]\d+(?:[_.]\d+)?)\b/', $userAgent, $m)) {
            return 'Mac OS X ' . str_replace('_', '.', $m[1]);
        }
        if (preg_match('/\bLinux\b/', $userAgent)) {
            return 'Linux';
        }

        return 'Escritorio';
    }
}
