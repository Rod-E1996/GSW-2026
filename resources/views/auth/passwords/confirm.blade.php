@extends('layouts.public.app')

@section('title')
    Confirmar contraseña
@endsection

@section('content')

<div class="auth-wrapper">

    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-mark" aria-hidden="true">
                <i class="bi bi-lock"></i>
            </div>
            <h1 class="auth-title">Confirma tu contraseña</h1>
            <p class="auth-subtitle">Por seguridad, vuelve a ingresarla para continuar</p>
        </div>

        <form method="POST" action="{{ route('password.confirm') }}" novalidate>
            @csrf

            <div class="auth-field">
                <label for="password" class="auth-label">Contraseña</label>
                <input id="password"
                       type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       name="password"
                       placeholder="••••••••"
                       required
                       autocomplete="current-password"
                       autofocus>

                @error('password')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary auth-submit">Confirmar</button>

        </form>

        <div class="auth-footer">
            ¿Olvidaste tu contraseña?
            <a href="{{ route('password.request') }}" class="auth-link">Recupérala</a>
        </div>

    </div>

</div>

@endsection
