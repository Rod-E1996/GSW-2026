@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Sesiones activas del sistema' }}
@endsection

@section('content')
@php
    $hayFiltros = request()->filled('search') || request()->filled('dispositivo');
    $moviles    = $dispositivos['Mobile'] + $dispositivos['Tablet'];
@endphp
<div class="container">

    {{-- Estadisticas --}}
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="ses-stat">
                    <div class="ses-stat__icon ses-stat__icon--primary"><i class="bi bi-broadcast"></i></div>
                    <div>
                        <div class="ses-stat__value">{{ $total_activas }}</div>
                        <div class="ses-stat__label">Sesiones abiertas</div>
                        <div class="ses-stat__hint">En todo el sistema</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="ses-stat">
                    <div class="ses-stat__icon ses-stat__icon--success"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="ses-stat__value">{{ $usuarios_conectados }}</div>
                        <div class="ses-stat__label">Usuarios conectados</div>
                        <div class="ses-stat__hint">De {{ $usuarios_total }} {{ $usuarios_total == 1 ? 'usuario activo' : 'usuarios activos' }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="ses-stat">
                    <div class="ses-stat__icon ses-stat__icon--info"><i class="bi bi-laptop"></i></div>
                    <div>
                        <div class="ses-stat__value">{{ $dispositivos['Desktop'] }}</div>
                        <div class="ses-stat__label">Desde escritorio</div>
                        <div class="ses-stat__hint">Computadoras y portátiles</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="ses-stat">
                    <div class="ses-stat__icon ses-stat__icon--warning"><i class="bi bi-phone"></i></div>
                    <div>
                        <div class="ses-stat__value">{{ $moviles }}</div>
                        <div class="ses-stat__label">Desde móvil</div>
                        <div class="ses-stat__hint">Móvil {{ $dispositivos['Mobile'] }} · Tableta {{ $dispositivos['Tablet'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Listado --}}
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ url('usuario/sessiones') }}" accept-charset="UTF-8" role="search">
                <div class="row g-2 align-items-center">
                    <div class="col-lg-5">
                        <div class="d-flex flex-wrap gap-2 my-2">
                            <a href="{{ url('usuario') }}" class="btn btn-secondary" data-bs-toggle="tooltip" title="Volver a usuarios">
                                <i class="fas fa-arrow-left"></i>&nbsp;Atrás
                            </a>
                            <a href="{{ url('usuario/sessiones') }}" class="btn btn-info" data-bs-toggle="tooltip" title="Volver a consultar">
                                <i class="fas fa-sync-alt"></i>&nbsp;Actualizar
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="ses-filters justify-content-lg-end my-2">
                            <select name="dispositivo" class="form-select w-auto" onchange="this.form.submit()" aria-label="Filtrar por dispositivo">
                                <option value="">Todos los dispositivos</option>
                                <option value="Desktop" {{ request('dispositivo') == 'Desktop' ? 'selected' : '' }}>Escritorio</option>
                                <option value="Mobile"  {{ request('dispositivo') == 'Mobile'  ? 'selected' : '' }}>Móvil</option>
                                <option value="Tablet"  {{ request('dispositivo') == 'Tablet'  ? 'selected' : '' }}>Tableta</option>
                            </select>
                            <div class="input-group w-auto">
                                <input type="text" class="form-control border" name="search" placeholder="Usuario, correo o IP..." value="{{ request('search') }}">
                                <span class="input-group-append">
                                    <button class="btn btn-info" type="submit" data-bs-toggle="tooltip" title="Buscar">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </span>
                            </div>
                            @if($hayFiltros)
                                <a href="{{ url('usuario/sessiones') }}" class="btn btn-secondary" data-bs-toggle="tooltip" title="Quitar filtros">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <!-- /.card-header -->

        <div class="card-body pt-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                <span class="text-theme-muted small">
                    Mostrando {{ $paginado->count() }} de {{ $paginado->total() }} {{ $paginado->total() == 1 ? 'sesión' : 'sesiones' }}
                    @if($hayFiltros) <span class="ses-badge ses-badge--info">Filtrado</span> @endif
                </span>
                <small class="text-theme-muted">
                    <i class="bi bi-info-circle"></i>&nbsp;Una sesión expira automáticamente tras {{ $lifetime }} minutos sin actividad
                </small>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover border ses-table text-center">
                    <thead class="text-uppercase">
                        <tr>
                            <th class="text-start">Usuario</th>
                            <th class="text-start">Dispositivo</th>
                            <th>IP</th>
                            <th>Inicio de sesión</th>
                            <th>Última actividad</th>
                            <th>Expira</th>
                            <th>Estado</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessiones as $s)
                            @php
                                $nombre    = $s->user->name ?? 'Usuario eliminado';
                                $iniciales = collect(explode(' ', trim($nombre)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                                $restante  = max(0, now()->diffInMinutes($s->expira, false));
                                $pct       = $lifetime > 0 ? min(100, max(0, ($restante / $lifetime) * 100)) : 0;
                                $barClass  = $pct > 50 ? '' : ($pct > 20 ? 'ses-expira__bar--warning' : 'ses-expira__bar--danger');
                            @endphp
                            <tr>
                                <td>
                                    <div class="ses-user">
                                        <div class="ses-avatar ses-avatar--sm" aria-hidden="true">{{ $iniciales ?: 'U' }}</div>
                                        <div>
                                            <div class="ses-user__name">{{ $nombre }}</div>
                                            <div class="ses-user__mail">{{ $s->user->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="ses-device">
                                        <div class="ses-device__icon" data-bs-toggle="tooltip" title="{{ $s->device_type_es }}"><i class="{{ $s->icono }}"></i></div>
                                        <div>
                                            <div class="ses-device__name">{{ $s->platform }}</div>
                                            <div class="ses-device__sub">{{ $s->browser }}{{ $s->device_model ? ' · ' . $s->device_model : '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="ses-code">{{ $s->ip_address }}</span></td>
                                <td>
                                    @if($s->inicio)
                                        <div class="ses-time-main">{{ \Carbon\Carbon::parse($s->inicio)->format('Y-m-d') }}</div>
                                        <div class="ses-time-sub">{{ \Carbon\Carbon::parse($s->inicio)->format('h:i:s A') }}</div>
                                    @else
                                        <span class="text-theme-muted">No registrado</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ses-time-main">{{ ucfirst($s->ultima_actividad->diffForHumans()) }}</div>
                                    <div class="ses-time-sub">{{ $s->ultima_actividad->format('Y-m-d h:i:s A') }}</div>
                                </td>
                                <td>
                                    <div class="ses-time-main">{{ (int) round($restante) }} min</div>
                                    <div class="ses-expira mx-auto mt-1" data-bs-toggle="tooltip" title="Tiempo restante antes de expirar por inactividad">
                                        <span class="ses-expira__bar {{ $barClass }}" style="width: {{ $pct }}%; display:block;"></span>
                                    </div>
                                </td>
                                <td>
                                    @if($s->es_actual)
                                        <span class="ses-badge ses-badge--success" data-bs-toggle="tooltip" title="Es la sesión con la que estás navegando ahora">
                                            <span class="ses-dot ses-dot--pulse"></span>Tu sesión
                                        </span>
                                    @else
                                        <span class="ses-badge ses-badge--success"><span class="ses-dot ses-dot--pulse"></span>Activa</span>
                                    @endif
                                </td>
                                <td>
                                    @can('usuario_sessiones')
                                        @if($s->user)
                                            <a href="{{ url('usuario/' . $s->user->id . '/sessiones') }}" class="btn my-sm btn-sm btn-secondary" data-bs-toggle="tooltip" title="Ver todas las sesiones del usuario">
                                                <i class="fas fa-list"></i>
                                            </a>
                                        @endif
                                    @endcan
                                    @can('session_cerrar')
                                        @if($s->es_actual)
                                            <button type="button" class="btn my-sm btn-sm btn-danger" disabled data-bs-toggle="tooltip" title="No puedes cerrar tu propia sesión desde aquí">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        @else
                                            <form action="{{ route('session_cerrar', ['session_id' => $s->id]) }}" method="POST" class="d-inline-block" id="formS{{ $loop->iteration }}">
                                                @csrf
                                                <button type="button" class="btn my-sm btn-sm btn-danger" data-bs-toggle="tooltip" title="Cerrar esta sesión"
                                                    onclick="alerta('formS{{ $loop->iteration }}', '¿Está seguro de cerrar la sesión de {{ addslashes($nombre) }} en {{ addslashes($s->platform) }} ({{ addslashes($s->browser) }})?', 'Sí, cerrar')">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="ses-empty">
                                        <i class="bi bi-shield-check"></i>
                                        <h5>{{ $hayFiltros ? 'Sin resultados' : 'Sin sesiones abiertas' }}</h5>
                                        <p class="mb-0">
                                            {{ $hayFiltros ? 'Ninguna sesión activa coincide con los filtros aplicados.' : 'No hay ningún usuario con sesión activa en este momento.' }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <!-- /.card-body -->

        <div class="card-footer clearfix text-center">
            <label>
                {!! $paginado->onEachSide(1)->render() !!}
            </label>
        </div>
    </div>
</div>
@endsection
