@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Sesiones de ' . $usuario->name }}
@endsection

@section('content')
@php
    $iniciales = collect(explode(' ', trim($usuario->name)))
        ->filter()
        ->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('');

    $cerrables      = $sessiones->where('es_actual', false)->count();
    $masReciente    = $sessiones->sortByDesc('ultima_actividad')->first();
    $tiposDetectados = collect($dispositivos)->filter()->count();
@endphp
<div class="container">

    {{-- Cabecera del usuario --}}
    <div class="card">
        <div class="card-body pt-3">
            <div class="ses-hero">
                <div class="ses-avatar" aria-hidden="true">{{ $iniciales ?: 'U' }}</div>

                <div class="flex-grow-1">
                    <h5 class="mb-0 fw-semibold">{{ $usuario->name }}</h5>
                    <div class="text-theme-muted small"><i class="bi bi-envelope"></i>&nbsp;{{ $usuario->email }}</div>
                    <div class="mt-2 d-flex flex-wrap gap-1 align-items-center">
                        @foreach($usuario->getRoleNames() as $rol)
                            <span class="badge bg-info">{{ $rol }}</span>
                        @endforeach
                        @if( $usuario->estado == 1 )
                            <span class="ses-badge ses-badge--success"><span class="ses-dot"></span>Usuario activo</span>
                        @else
                            <span class="ses-badge ses-badge--danger"><span class="ses-dot"></span>Usuario inactivo</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ url('usuario') }}" class="btn btn-secondary" data-bs-toggle="tooltip" title="Volver al listado">
                        <i class="fas fa-arrow-left"></i>&nbsp;Atrás
                    </a>
                    @can('usuario_show')
                        <a href="{{ url('usuario/' . $usuario->id) }}" class="btn btn-primary" data-bs-toggle="tooltip" title="Ver ficha del usuario">
                            <i class="fas fa-user"></i>&nbsp;Ver usuario
                        </a>
                    @endcan
                    <a href="{{ url('usuario/' . $usuario->id . '/sessiones') }}" class="btn btn-info" data-bs-toggle="tooltip" title="Volver a consultar">
                        <i class="fas fa-sync-alt"></i>&nbsp;Actualizar
                    </a>
                    @can('usuario_cerrar_todas_sessiones')
                        <form action="{{ route('usuario_cerrar_todas_sessiones', $usuario->id) }}" method="POST" class="d-inline-block" id="formCerrarTodas">
                            @csrf
                            <button type="button" class="btn btn-danger" {{ $cerrables == 0 ? 'disabled' : '' }}
                                data-bs-toggle="tooltip" title="{{ $cerrables == 0 ? 'No hay sesiones que cerrar' : 'Cierra la sesión en todos los dispositivos del usuario' }}"
                                onclick="alerta('formCerrarTodas', '¿Está seguro de cerrar TODAS las sesiones de {{ addslashes($usuario->name) }}? El usuario tendrá que volver a iniciar sesión en todos sus dispositivos.', 'Sí, cerrar todas')">
                                <i class="fas fa-power-off"></i>&nbsp;Cerrar todas ({{ $cerrables }})
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- Estadisticas --}}
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="ses-stat">
                    <div class="ses-stat__icon ses-stat__icon--primary"><i class="bi bi-broadcast"></i></div>
                    <div>
                        <div class="ses-stat__value">{{ $sessiones->count() }}</div>
                        <div class="ses-stat__label">Sesiones abiertas</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="ses-stat">
                    <div class="ses-stat__icon ses-stat__icon--info"><i class="bi bi-laptop"></i></div>
                    <div>
                        <div class="ses-stat__value">{{ $tiposDetectados }}</div>
                        <div class="ses-stat__label">Tipos de dispositivo</div>
                        <div class="ses-stat__hint">
                            Escritorio {{ $dispositivos['Desktop'] }} · Móvil {{ $dispositivos['Mobile'] }} · Tableta {{ $dispositivos['Tablet'] }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="ses-stat">
                    <div class="ses-stat__icon ses-stat__icon--success"><i class="bi bi-activity"></i></div>
                    <div>
                        <div class="ses-stat__value" style="font-size: 18px;">
                            {{ $masReciente ? ucfirst($masReciente->ultima_actividad->diffForHumans()) : 'Sin actividad' }}
                        </div>
                        <div class="ses-stat__label">Última actividad</div>
                        @if($masReciente)
                            <div class="ses-stat__hint">{{ $masReciente->ultima_actividad->format('Y-m-d h:i:s A') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="ses-stat">
                    <div class="ses-stat__icon ses-stat__icon--warning"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <div class="ses-stat__value" style="font-size: 18px;">
                            {{ $ultima_conexion ? ucfirst(\Carbon\Carbon::parse($ultima_conexion->created_at)->diffForHumans()) : 'Nunca' }}
                        </div>
                        <div class="ses-stat__label">Último inicio de sesión</div>
                        <div class="ses-stat__hint">
                            {{ $total_historico }} {{ $total_historico == 1 ? 'inicio registrado' : 'inicios registrados' }}
                            @if($total_historico > 0)
                                · <a href="#historialSesiones" class="ses-link" data-bs-toggle="collapse" data-bs-target="#historialSesiones" aria-controls="historialSesiones">Ver historial</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Lista de sesiones --}}
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold"><i class="bi bi-shield-lock"></i>&nbsp;Sesiones abiertas</span>
            <small class="text-theme-muted">
                <i class="bi bi-info-circle"></i>&nbsp;Una sesión expira automáticamente tras {{ $lifetime }} minutos sin actividad
            </small>
        </div>

        <div class="card-body pt-3 d-grid gap-3">
            @forelse($sessiones as $s)
                @php
                    $restante = max(0, now()->diffInMinutes($s->expira, false));
                    $pct      = $lifetime > 0 ? min(100, max(0, ($restante / $lifetime) * 100)) : 0;
                    $barClass = $pct > 50 ? '' : ($pct > 20 ? 'ses-expira__bar--warning' : 'ses-expira__bar--danger');
                @endphp
                <div class="ses-item {{ $s->es_actual ? 'ses-item--actual' : '' }}">
                    <div class="ses-item__device" data-bs-toggle="tooltip" title="{{ $s->device_type_es }}">
                        <i class="{{ $s->icono }}"></i>
                    </div>

                    <div class="ses-item__main">
                        <div class="ses-item__title">
                            {{ $s->platform }}
                            <span class="ses-badge ses-badge--neutral">{{ $s->device_type_es }}</span>
                        </div>
                        <div class="ses-item__meta">
                            <span data-bs-toggle="tooltip" title="Navegador"><i class="bi bi-window"></i>{{ $s->browser }}</span>
                            <span data-bs-toggle="tooltip" title="Dirección IP"><i class="bi bi-geo-alt"></i>{{ $s->ip_address }}</span>
                            @if($s->device_model)
                                <span data-bs-toggle="tooltip" title="Modelo"><i class="bi bi-cpu"></i>{{ $s->device_model }}</span>
                            @endif
                            <span data-bs-toggle="tooltip" title="Identificador de sesión">
                                <i class="bi bi-key"></i><span class="ses-code">{{ substr($s->id, 0, 12) }}…</span>
                            </span>
                        </div>
                    </div>

                    <div class="ses-item__times">
                        <span>
                            <i class="bi bi-box-arrow-in-right"></i>&nbsp;Inicio:
                            <strong>{{ $s->inicio ? \Carbon\Carbon::parse($s->inicio)->format('Y-m-d h:i A') : 'No registrado' }}</strong>
                        </span>
                        <span>
                            <i class="bi bi-activity"></i>&nbsp;Última actividad:
                            <strong>{{ ucfirst($s->ultima_actividad->diffForHumans()) }}</strong>
                            <small>({{ $s->ultima_actividad->format('h:i:s A') }})</small>
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            <span><i class="bi bi-hourglass-split"></i>&nbsp;Expira en <strong>{{ (int) round($restante) }} min</strong></span>
                            <span class="ses-expira flex-grow-1" data-bs-toggle="tooltip" title="Tiempo restante antes de expirar por inactividad">
                                <span class="ses-expira__bar {{ $barClass }}" style="width: {{ $pct }}%;"></span>
                            </span>
                        </span>
                    </div>

                    <div class="ses-item__actions">
                        @if($s->es_actual)
                            <span class="ses-badge ses-badge--success" data-bs-toggle="tooltip" title="Es la sesión con la que estás navegando ahora; ciérrala desde tu perfil">
                                <span class="ses-dot ses-dot--pulse"></span>Tu sesión actual
                            </span>
                        @else
                            <span class="ses-badge ses-badge--success"><span class="ses-dot ses-dot--pulse"></span>Activa</span>
                            @can('usuario_cerrar_session')
                                <form action="{{ route('usuario_cerrar_session', ['id' => $usuario->id, 'session_id' => $s->id]) }}" method="POST" class="d-inline-block" id="formS{{ $loop->iteration }}">
                                    @csrf
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Cerrar esta sesión"
                                        onclick="alerta('formS{{ $loop->iteration }}', '¿Está seguro de cerrar la sesión de {{ addslashes($usuario->name) }} en {{ addslashes($s->platform) }} ({{ addslashes($s->browser) }})?', 'Sí, cerrar')">
                                        <i class="fas fa-power-off"></i>&nbsp;Cerrar
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            @empty
                <div class="ses-empty">
                    <i class="bi bi-shield-check"></i>
                    <h5>Sin sesiones abiertas</h5>
                    <p class="mb-0">{{ $usuario->name }} no tiene ninguna sesión activa en este momento.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Historial de inicios de sesion (desplegable) --}}
    @php $idsActivas = $sessiones->pluck('id')->all(); @endphp
    <div class="card">
        <a class="card-header ses-collapse-header d-flex flex-wrap justify-content-between align-items-center gap-2 text-decoration-none {{ $historial_abierto ? '' : 'collapsed' }}"
            data-bs-toggle="collapse" href="#historialSesiones" role="button" aria-expanded="{{ $historial_abierto ? 'true' : 'false' }}" aria-controls="historialSesiones">
            <span class="fw-semibold">
                <i class="bi bi-clock-history"></i>&nbsp;Historial de inicios de sesión
                <span class="ses-badge ses-badge--neutral ms-1">{{ $total_historico }}</span>
            </span>
            <span class="d-flex align-items-center gap-3">
                <small class="text-theme-muted">Fecha y hora de cada inicio, del más reciente al más antiguo</small>
                <i class="bi bi-chevron-down ses-collapse-icon"></i>
            </span>
        </a>

        <div id="historialSesiones" class="collapse {{ $historial_abierto ? 'show' : '' }}">
            <div class="card-body pt-3">
                @if($historial->total() > 0)
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                        <span class="text-theme-muted small">
                            Mostrando {{ $historial->firstItem() }}–{{ $historial->lastItem() }} de {{ $historial->total() }} {{ $historial->total() == 1 ? 'inicio' : 'inicios' }}
                        </span>
                        <span class="d-flex flex-wrap gap-2">
                            <span class="ses-badge ses-badge--success"><span class="ses-dot"></span>Activa</span>
                            <span class="ses-badge ses-badge--neutral"><span class="ses-dot"></span>Cerrada</span>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover border ses-table text-center">
                            <thead class="text-uppercase">
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th class="text-start">Dispositivo</th>
                                    <th>IP</th>
                                    <th>Hace</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($historial as $h)
                                    @php
                                        $fecha    = \Carbon\Carbon::parse($h->created_at);
                                        $esActual = $h->session_id === $session_actual;
                                        $activa   = in_array($h->session_id, $idsActivas, true);
                                    @endphp
                                    <tr>
                                        <td class="text-theme-muted">{{ $historial->total() - $historial->firstItem() - $loop->index + 1 }}</td>
                                        <td>
                                            <div class="ses-time-main">{{ $fecha->format('Y-m-d') }}</div>
                                            <div class="ses-time-sub">{{ ucfirst($fecha->translatedFormat('l')) }}</div>
                                        </td>
                                        <td><div class="ses-time-main">{{ $fecha->format('h:i:s A') }}</div></td>
                                        <td>
                                            <div class="ses-device">
                                                <div class="ses-device__icon" data-bs-toggle="tooltip" title="{{ app(\App\Services\SessionesService::class)->tipoDispositivo($h->device_type) }}">
                                                    <i class="{{ app(\App\Services\SessionesService::class)->icono($h->device_type) }}"></i>
                                                </div>
                                                <div>
                                                    <div class="ses-device__name">{{ $h->platform ?: 'Desconocido' }}</div>
                                                    <div class="ses-device__sub">{{ $h->browser ?: 'Desconocido' }}{{ $h->device_model ? ' · ' . $h->device_model : '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="ses-code">{{ $h->ip_address ?: '—' }}</span></td>
                                        <td class="text-theme-muted">{{ ucfirst($fecha->diffForHumans()) }}</td>
                                        <td>
                                            @if($esActual)
                                                <span class="ses-badge ses-badge--success"><span class="ses-dot ses-dot--pulse"></span>Tu sesión actual</span>
                                            @elseif($activa)
                                                <span class="ses-badge ses-badge--success"><span class="ses-dot ses-dot--pulse"></span>Activa</span>
                                            @else
                                                <span class="ses-badge ses-badge--neutral"><span class="ses-dot"></span>Cerrada</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="ses-empty">
                        <i class="bi bi-clock-history" style="color: var(--color-text-muted);"></i>
                        <h5>Sin inicios registrados</h5>
                        <p class="mb-0">Todavía no hay inicios de sesión registrados para {{ $usuario->name }}.</p>
                    </div>
                @endif
            </div>
            @if($historial->hasPages())
                <div class="card-footer clearfix text-center">
                    <label>
                        {!! $historial->onEachSide(1)->render() !!}
                    </label>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
