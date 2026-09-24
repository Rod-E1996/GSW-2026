@extends('layouts.public.app')

@section('title')
    Restablecer contraseña
@endsection

@section('content')

<div class="auth-wrapper">

    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-mark" aria-hidden="true">
                <i class="bi bi-shield-check"></i>
            </div>
            <h1 class="auth-title">Nueva contraseña</h1>
            <p class="auth-subtitle">Elige una contraseña para tu cuenta</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="auth-field">
                <label for="email" class="auth-label">Correo electrónico</label>
                <input id="email"
                       type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       name="email"
                       value="{{ $email ?? old('email') }}"
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
                <label for="password" class="auth-label">Nueva contraseña</label>
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

            <button type="submit" class="btn btn-primary auth-submit">Restablecer contraseña</button>

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
