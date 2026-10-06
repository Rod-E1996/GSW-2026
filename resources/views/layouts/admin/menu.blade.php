{{-- Obtener la ruta actual --}}
@php $route = Route::current()->getName();  @endphp

<!-- ======= Sidebar ======= -->
<aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">

        <li class="nav-heading">Pagina publica</li>

        <li class="nav-item">
            <a class="nav-link {{ Request::is('/') ? '' : 'collapsed' }}" href="{{ url('/') }}">
                <i class="bi bi-globe"></i>
                <span>Pagina publica</span>
            </a>
        </li>

        <li class="nav-heading">Hotel</li>

        @can('tipo_habitacion_index')
            <li class="nav-item">
                <a class="nav-link {{ Request::is('tipo_habitacion*') ? '' : 'collapsed' }}" href="{{ url('tipo_habitacion') }}">
                    <i class="bi bi-door-open"></i>
                    <span>Tipos de habitación</span>
                </a>
            </li>
        @endcan

        @can('habitacion_index')
            <li class="nav-item">
                <a class="nav-link {{ Request::is('habitacion*') ? '' : 'collapsed' }}" href="{{ url('habitacion') }}">
                    <i class="bi bi-building"></i>
                    <span>Habitaciones</span>
                </a>
            </li>
        @endcan

        <li class="nav-heading">Administración</li>

        @can('dashboard')
            <li class="nav-item">
                <a class="nav-link {{ Request::is('dashboard*') ? '' : 'collapsed' }}" href="{{ url('dashboard') }}">
                    <i class="bi bi-grid"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        @endcan

        {{-- Error log migrado a React --}}

        @can('ejemplo_index')
            <li class="nav-item">
                <a class="nav-link {{ Request::is('ejemplo*') ? '' : 'collapsed' }}" href="{{ url('ejemplo') }}">
                    <i class="fa fa-info-circle"></i>
                    <span>Crud de ejemplo</span>
                </a>
            </li>
        @endcan

        @can('queue_control_index')
            <li class="nav-item">
                <a class="nav-link {{ Request::is('queue_control*') ? '' : 'collapsed' }}" href="{{ url('queue_control') }}">
                    <i class="bi bi-bar-chart-fill"></i>
                    <span>Procesos en segundo plano</span>
                </a>
            </li>
        @endcan

        @if (auth()->user()->can('permiso_index') || auth()->user()->can('role_index') || auth()->user()->can('usuario_index'))
            @php
                $submenuActivo = Request::is('permiso*') || Request::is('role*') || Request::is('usuario*');
            @endphp

            <li class="nav-item">
                <a class="nav-link {{ $submenuActivo ? '' : 'collapsed' }}" data-bs-target="#tables-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-layout-text-window-reverse"></i><span>Roles y permisos</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="tables-nav" class="nav-content {{ $submenuActivo ? 'show' : 'collapse' }}" data-bs-parent="#sidebar-nav">
                    @can('permiso_index')
                        <li>
                            <a class="{{ Request::is('permiso*') ? 'active' : 'collapsed' }}" href="{{ url('permiso') }}">
                                <i class="bi bi-circle"></i><span>Permisos</span>
                            </a>
                        </li>
                    @endcan

                    @can('role_index')
                        <li>
                            <a class="{{ Request::is('role*') ? 'active' : 'collapsed' }}" href="{{ url('role') }}">
                                <i class="bi bi-circle"></i><span>Roles</span>
                            </a>
                        </li>
                    @endcan

                    @can('usuario_index')
                        <li>
                            <a class="{{ Request::is('usuario*') ? 'active' : 'collapsed' }}" href="{{ url('usuario') }}">
                                <i class="bi bi-circle"></i><span>Usuarios</span>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>
        @endif

    </ul>

</aside>
<!-- End Sidebar-->
