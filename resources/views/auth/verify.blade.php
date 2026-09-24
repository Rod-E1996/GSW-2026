@extends('layouts.public.app')

@section('title')
    Verificar correo
@endsection

@section('content')

<div class="auth-wrapper">

    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-mark" aria-hidden="true">
                <i class="bi bi-envelope-check"></i>
            </div>
            <h1 class="auth-title">Verifica tu correo</h1>
            <p class="auth-subtitle">Te enviamos un enlace de verificación</p>
        </div>

        @if (session('resent'))
            <div class="alert alert-success" role="alert">
                Enviamos un nuevo enlace de verificación a tu correo.
            </div>
        @endif

        <p class="auth-note">
            Antes de continuar, revisa tu bandeja de entrada y abre el enlace que
            te enviamos. Si no lo encuentras, recuerda mirar en la carpeta de
            correo no deseado.
        </p>

        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="btn btn-primary auth-submit">Reenviar enlace</button>
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
