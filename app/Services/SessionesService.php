<?php

namespace App\Services;

use App\Models\Session;
use App\Models\SessionLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session as LaravelSession;
use Jenssegers\Agent\Agent;

/**
 * Centraliza la lectura y el cierre de las sesiones almacenadas en la
 * tabla "sessions" (driver database). Lo usan la pantalla de sesiones
 * por usuario y la pantalla global de sesiones del sistema.
 */
class SessionesService
{
    //Tiempo de vida de una sesion en minutos (config/session.php)
    public function lifetime(): int
    {
        return (int) config('session.lifetime', 120);
    }

    //Timestamp minimo de "last_activity" para considerar que una sesion sigue abierta
    public function limiteActividad(): int
    {
        return now()->subMinutes($this->lifetime())->timestamp;
    }

    //Id de la sesion con la que el usuario logueado esta navegando ahora mismo
    public function sessionActualId(): ?string
    {
        return LaravelSession::getId();
    }

    //Query base: sesiones autenticadas que todavia no expiraron
    public function activas(): Builder
    {
        return Session::query()
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $this->limiteActividad());
    }

    //Query de sesiones activas de un usuario puntual
    public function activasDeUsuario(int $userId): Builder
    {
        return $this->activas()->where('user_id', $userId);
    }

    /**
     * Convierte una coleccion de sesiones en objetos listos para la vista,
     * cruzando cada una con su registro en "sessions_logs" (si existe).
     */
    public function detallar(Collection $sessions): Collection
    {
        $logs = SessionLog::query()
            ->whereIn('session_id', $sessions->pluck('id'))
            ->orderBy('id')
            ->get()
            ->keyBy('session_id');

        return $sessions->map(fn (Session $session) => $this->describir($session, $logs->get($session->id)));
    }

    /**
     * Arma la descripcion de una sesion. Si no hay registro en sessions_logs
     * (por ejemplo, sesiones anteriores a esa tabla) se interpreta el
     * user agent guardado en la propia sesion.
     */
    public function describir(Session $session, ?SessionLog $log = null): object
    {
        $agent = new Agent();
        $agent->setUserAgent((string) $session->user_agent);

        $platform = $log->platform ?? trim(($agent->platform() ?: 'Desconocido') . ' ' . $agent->version($agent->platform()));
        $browser  = $log->browser ?? trim(($agent->browser() ?: 'Desconocido') . ' ' . $agent->version($agent->browser()));

        if ($log && $log->device_type) {
            $deviceType = $log->device_type;
        } elseif ($agent->isTablet()) {
            $deviceType = 'Tablet';
        } elseif ($agent->isMobile()) {
            $deviceType = 'Mobile';
        } elseif ($agent->isDesktop()) {
            $deviceType = 'Desktop';
        } else {
            $deviceType = 'Unknown';
        }

        $ultimaActividad = Carbon::createFromTimestamp((int) $session->last_activity, config('app.timezone'));
        $expira = $ultimaActividad->copy()->addMinutes($this->lifetime());

        return (object) [
            'id'               => $session->id,
            'user'             => $session->user,
            'ip_address'       => $log->ip_address ?? $session->ip_address ?? 'Desconocida',
            'platform'         => $platform !== '' ? $platform : 'Desconocido',
            'browser'          => $browser !== '' ? $browser : 'Desconocido',
            'device_type'      => $deviceType,
            'device_type_es'   => $this->tipoDispositivo($deviceType),
            'device_model'     => $log->device_model ?? null,
            'icono'            => $this->icono($deviceType),
            'inicio'           => $log?->created_at,
            'ultima_actividad' => $ultimaActividad,
            'expira'           => $expira,
            'es_actual'        => $session->id === $this->sessionActualId(),
            'user_agent'       => $session->user_agent,
        ];
    }

    //Estadisticas por tipo de dispositivo de un conjunto de sesiones activas
    public function conteoPorDispositivo(Builder $query): array
    {
        $conteo = SessionLog::query()
            ->whereIn('session_id', (clone $query)->select('id'))
            ->selectRaw('device_type, COUNT(*) as total')
            ->groupBy('device_type')
            ->pluck('total', 'device_type');

        return [
            'Desktop' => (int) ($conteo['Desktop'] ?? 0),
            'Mobile'  => (int) ($conteo['Mobile'] ?? 0),
            'Tablet'  => (int) ($conteo['Tablet'] ?? 0),
            'Unknown' => (int) ($conteo['Unknown'] ?? 0),
        ];
    }

    /**
     * Cierra una sesion: elimina la fila de "sessions" (el usuario queda
     * deslogueado en ese dispositivo al instante) y marca el registro de
     * "sessions_logs" como cerrado para dejar rastro en la auditoria.
     * Nunca cierra la sesion con la que el administrador esta navegando.
     */
    public function cerrar(Session $session): bool
    {
        if ($session->id === $this->sessionActualId()) {
            return false;
        }

        SessionLog::where('session_id', $session->id)
            ->where('estado', 1)
            ->get()
            ->each(function (SessionLog $log) {
                $log->estado = 0;
                $log->save();
            });

        return (bool) $session->delete();
    }

    //Cierra todas las sesiones de un usuario (excepto la actual del administrador)
    public function cerrarTodasDeUsuario(int $userId): int
    {
        $cerradas = 0;

        Session::query()
            ->where('user_id', $userId)
            ->get()
            ->each(function (Session $session) use (&$cerradas) {
                if ($this->cerrar($session)) {
                    $cerradas++;
                }
            });

        return $cerradas;
    }

    public function tipoDispositivo(?string $deviceType): string
    {
        return match ($deviceType) {
            'Desktop' => 'Escritorio',
            'Mobile'  => 'Móvil',
            'Tablet'  => 'Tableta',
            default   => 'Desconocido',
        };
    }

    public function icono(?string $deviceType): string
    {
        return match ($deviceType) {
            'Desktop' => 'bi bi-laptop',
            'Mobile'  => 'bi bi-phone',
            'Tablet'  => 'bi bi-tablet',
            default   => 'bi bi-question-circle',
        };
    }
}
