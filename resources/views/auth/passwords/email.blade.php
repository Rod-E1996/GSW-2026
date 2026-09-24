@extends('layouts.public.app')

@section('title')
    Recuperar contraseña
@endsection

@section('content')

<div class="auth-wrapper">

    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-mark" aria-hidden="true">
                <i class="bi bi-key"></i>
            </div>
            <h1 class="auth-title">Recuperar contraseña</h1>
            <p class="auth-subtitle">Te enviaremos un enlace para restablecerla</p>
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf

            <div class="auth-field">
                <label for="email" class="auth-label">Correo electrónico</label>
                <input id="email"
                       type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       name="email"
                       value="{{ old('email') }}"
                       placeholder="nombre@ejemplo.com"
                       required
                       autocomplete="email"
                       autofocus>

                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary auth-submit">Enviar enlace</button>

        </form>

        <div class="auth-footer">
            <a href="{{ route('login') }}" class="auth-back">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Volver a iniciar sesión
            </a>
        </div>

    </div>

</div>

@endsection
