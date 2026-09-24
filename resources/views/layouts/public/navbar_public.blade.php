<nav class="navbar navbar-expand-lg navbar-light navbar-shadow">
    <div class="container-fluid">

        <a href="{{ route('home') }}" class="navbar-brand">
            {{-- <img src="{{ asset('NiceAdmin/assets/img/favicon.png')}}" alt="{{ config('app.name' )}}"> --}}
            {{ config('app.name')}}
        </a>

        <button class="navbar-toggler bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto">

                <li class="nav-item mx-2">
                    <a href="{{ route('home') }}" class="text-black"><i class="fas fa-home"></i> Inicio</a>
                </li>

                @if(Auth::check() && auth()->user()->hasAnyRole(['Super Administrador', 'Administrador']))
                    <li class="nav-item mx-2">
                        <a href="{{ route('dashboard') }}" class="text-black"><i class="fas fa-cog"></i> Administración</a>
                    </li>
                @endif

            </ul>
            <ul class="navbar-nav align-items-lg-center">

                {{-- Selector de tema: claro / oscuro / sistema --}}
                @include('layouts.partials.theme-toggle', ['id' => 'Public'])

                @if(Auth::check())

                    <li class="nav-item dropdown">

                        <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                            <span class="d-md-block dropdown-toggle ps-2 text-black"> <i class="fas fa-user"></i>&nbsp;&nbsp; {{ auth()->user()->name ?? '' }}</span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">

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
                        <a href="{{ route('login') }}" class="text-black"><i class="fas fa-sign-in-alt"></i> Iniciar sesi&oacute;n</a>
                    </li>

                    <li class="nav-item mx-2">
                        <a href="{{ route('register') }}" class="text-black"><i class="fas fa-user"></i> Registrarse</a>
                    </li>

                @endif

            </ul>
        </div>

    </div>
</nav>
