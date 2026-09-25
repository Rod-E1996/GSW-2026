<nav class="navbar navbar-expand-lg navbar-light navbar-shadow sticky-top">
    <div class="container">

        <a href="{{ route('home') }}" class="navbar-brand fw-bold">
            <i class="bi bi-building" aria-hidden="true"></i>&nbsp;{{ config('hotel.nombre', config('app.name')) }}
        </a>

        <button class="navbar-toggler bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a href="{{ route('home') }}#habitaciones" class="nav-link site-nav-link">Habitaciones</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('home') }}#servicios" class="nav-link site-nav-link">Servicios</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('home') }}#galeria" class="nav-link site-nav-link">Galería</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('home') }}#contacto" class="nav-link site-nav-link">Contacto</a>
                </li>

                @auth
                    @can('dashboard')
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}" class="nav-link site-nav-link"><i class="fas fa-cog"></i> Administración</a>
                        </li>
                    @endcan
                @endauth

            </ul>
            <ul class="navbar-nav align-items-lg-center">

                {{-- Selector de tema: claro / oscuro / sistema --}}
                @include('layouts.partials.theme-toggle', ['id' => 'Public'])

                @if(Auth::check())

                    <li class="nav-item dropdown">

                        <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                            <span class="d-md-block dropdown-toggle ps-2 site-nav-link"> <i class="fas fa-user"></i>&nbsp;&nbsp; {{ auth()->user()->name ?? '' }}</span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">

                            @can('perfil_show')
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="{{ url('perfil') }}">
                                    <i class="fas fa-id-card"></i><span>&nbsp; Mi cuenta</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            @endcan

                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="fas fa-sign-out-alt"></i><span>&nbsp; Cerrar sesión</span>
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </li>

                        </ul>

                    </li>

                @else

                    <li class="nav-item mx-2">
                        <a href="{{ route('login') }}" class="nav-link site-nav-link"><i class="fas fa-sign-in-alt"></i> Iniciar sesi&oacute;n</a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Registrarse</a>
                    </li>

                @endif

            </ul>
        </div>

    </div>
</nav>
