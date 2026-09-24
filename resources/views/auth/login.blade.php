@extends('layouts.public.app')

@section('title')
    Iniciar sesión
@endsection

@section('content')

{{-- .auth-wrapper centra la tarjeta en los dos ejes ocupando el
     alto libre entre la barra de navegacion y el pie. --}}
<div class="auth-wrapper">

    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-mark" aria-hidden="true">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h1 class="auth-title">Iniciar sesión</h1>
            <p class="auth-subtitle">Ingresa tus credenciales para continuar</p>
        </div>

        <form method="POST" action="{{ route('login') }}" novalidate>
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

            <div class="auth-field">
                <label for="password" class="auth-label">Contraseña</label>
                <input id="password"
                       type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       name="password"
                       placeholder="••••••••"
                       required
                       autocomplete="current-password">

                @error('password')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="auth-meta">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember"
                           {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">Recordarme</label>
                </div>

                <a href="{{ route('password.request') }}" class="auth-link">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn btn-primary auth-submit">Iniciar sesión</button>

        </form>

        <div class="auth-footer">
            ¿No tienes cuenta?
            <a href="{{ url('register') }}" class="auth-link">Regístrate</a>
        </div>

    </div>

</div>

@endsection
