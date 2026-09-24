@extends('layouts.public.app')

@section('title')
  Inicio
@endsection

@section('content')

<div class="home-wrapper">

    <section class="home-hero">

        <div class="home-icon" aria-hidden="true">
            <i class="bi bi-layers"></i>
        </div>

        <h1 class="home-title">{{ config('app.name') }}</h1>

        <p class="home-lead">Todo listo para empezar a construir.</p>

        <div class="home-actions">
            @auth
                @can('dashboard')
                    <a href="{{ url('dashboard') }}" class="btn btn-primary">
                        <i class="bi bi-speedometer2" aria-hidden="true"></i>
                        Ir al panel
                    </a>
                @endcan
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    Iniciar sesión
                </a>
            @endauth
        </div>

    </section>

</div>

@endsection
