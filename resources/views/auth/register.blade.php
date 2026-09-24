@extends('layouts.public.app')

@section('title')
    Registrarse
@endsection

@section('content')

<div class="auth-wrapper">

    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-mark" aria-hidden="true">
                <i class="bi bi-person-plus"></i>
            </div>
            <h1 class="auth-title">Crear cuenta</h1>
            <p class="auth-subtitle">Completa tus datos para registrarte</p>
        </div>

        <form method="POST" action="{{ route('register') }}" novalidate>
            @csrf

            <div class="auth-field">
                <label for="name" class="auth-label">Nombre completo</label>
                <input id="name"
                       type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       name="name"
                       value="{{ old('name') }}"
                       placeholder="Tu nombre y apellido"
                       required
                       autocomplete="name"
                       autofocus>

                @error('name')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="auth-field">
                <label for="email" class="auth-label">Correo electrónico</label>
                <input id="email"
                       type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       name="email"
                       value="{{ old('email') }}"
                       placeholder="nombre@ejemplo.com"
                       required
                       autocomplete="email">

                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="auth-field">
                <label for="password" class="auth-label">Contraseña</label>
                <input id="password"
                       type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       name="password"
                       placeholder="••••••••"
                       required
                       autocomplete="new-password">

                @error('password')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="auth-field">
                <label for="password-confirm" class="auth-label">Confirmar contraseña</label>
                <input id="password-confirm"
                       type="password"
                       class="form-control"
                       name="password_confirmation"
                       placeholder="••••••••"
                       required
                       autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary auth-submit">Crear cuenta</button>

        </form>

        <div class="auth-footer">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="auth-link">Inicia sesión</a>
        </div>

    </div>

</div>

@endsection
