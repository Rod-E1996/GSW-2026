@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Perfil' }}
@endsection

@section('content')
<div class="container ">
    <a href="{{ url('dashboard') }}" class="btn btn-danger mb-2"><i class="fa fa-arrow-left"></i> Atrás</a>

    <div class="row">
        <div class="col-sm-12 col-md-8">
            <form class="card" method="POST" action="{{ url('perfil/editar') }}">
                @csrf
                <div class="card-header font-weight-bold h4">
                    Datos generales
                </div>
                <div class="card-body">
                    <div class="form-group  {{ $errors->has('name') ? 'has-error' : ''}}">
                        <label  class="floating-label" for="name">Nombre:</label>
                        <input type="text" name="name" class="form-control" id="name"
                            value="{{  old('name') ?? auth()->user()->name ?? '' }}" required>
                        {!! $errors->first('name', '<p class="text-danger">:message</p>') !!}
                    </div>
                    <div class="form-group {{ $errors->has('email') ? 'has-error' : ''}}">
                        <label  class="floating-label" for="email">Email:</label>
                        <input type="text" name="email" class="form-control" value="{{ old('email') ?? auth()->user()->email ?? '' }}" required id="email">
                        {!! $errors->first('email', '<p class="text-danger">:message</p>') !!}
                    </div>
                    <div class="form-group  {{ $errors->has('phone') ? 'has-error' : ''}}">
                        <label  class="floating-label" for="phone">Teléfono:</label>
                        <input type="text" name="phone" class="form-control" id="phone"
                            value="{{  old('phone') ?? auth()->user()->phone ?? '' }}" required>
                        {!! $errors->first('phone', '<p class="text-danger">:message</p>') !!}
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-success">Guardar cambios</button>
                </div>
            </form>
        </div>
        <div class="col-sm-12 col-md-4">
            <form class="card" method="POST" action="{{ url('perfil/editar-pass') }}">
                @csrf
                <div class="card-header font-weight-bold h4">
                    Editar Contraseña
                </div>
                <div class="card-body">
                    <div class="form-group {{ $errors->has('current_password') ? 'has-error' : ''}}">
                        <label class="floating-label" for="current_password">Contraseña Anterior:</label>
                        <input type="password" name="current_password" id="current_password" class="form-control">
                        {!! $errors->first('current_password', '<p class="text-danger">:message</p>') !!}
                    </div>
                    <div class="form-group {{ $errors->has('password') ? 'has-error' : ''}}">
                        <label class="floating-label" for="password">Nueva Contraseña:</label>
                        <input type="password" name="password" id="password" class="form-control">
                        {!! $errors->first('password', '<p class="text-danger">:message</p>') !!}
                    </div>
                    <div class="form-group">
                        <label class="floating-label" for="password_confirmation">Repetir Nueva Contraseña:</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control">
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-success" type="submit">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="padding: 10px 20px;">
        <div class="row p-3">
            <div class="col-md-6 col-12"><h5><b>Dispositivos</b></h5></div>
            <div class="col-md-6 col-12"><h5><b>Inicio de sesión</b></h5></div>
        </div>

        <div class="row" style="padding-left: 17px;">
            <div class="col-md-6 col-12">
                <div class="form-check form-switch">
                    <form action="{{ url('usuario/login_notificacion/' . $usuario->id ) }}" method="POST" class="d-inline-block py-1" id="formE">
                        @csrf
                        <input class="form-check-input" type="checkbox" id="flexSwitchCheckChecked" {{ ($usuario->login_notificacion != 1)?'':'checked' }} onchange="$('#formE').submit();">
                        <label class="form-check-label" for="flexSwitchCheckChecked">Notificar nuevos inicios de sesión <span class="badge bg-{{ $usuario->login_notificacion == 1 ? 'success' : 'danger'}} text-white">{{ $usuario->login_notificacion == 1 ? 'Activado' : 'Desactivado'}}</span></label>
                    </form>
                </div>
            </div>
        </div>

        @foreach ($sessiones_log as $val)
            <hr><div class="row mx-1">
                <div class="col-md-6 col-12">
                    <table>
                        <tr>
                            <td rowspan="2" style="padding-right: 15px;">
                                @if($val->device_type == 'Mobile')
                                    <i class="bi bi-phone" style="font-size: 30px;"></i>
                                @elseif($val->device_type == 'Desktop')
                                    <i class="bi bi-laptop" style="font-size: 30px;"></i>
                                @elseif($val->device_type == 'Tablet')
                                    <i class="bi bi-phone-landscape" style="font-size: 30px;"></i>
                                @else
                                    <i class="bi bi-search" style="font-size: 30px;"></i>
                                @endif
                            </td>
                            <td>
                                <b>{{ $val->platform }}</b>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <small class="text-theme-subtle">{{ $val->browser }}</small><br>
                                <small class="text-theme-subtle">{{ $val->ip_address }}</small>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6 col-12 mt-3">
                    <b><small class="text-theme-subtle"><i class="bi bi-clock"></i> {{ \Carbon\Carbon::parse($val->created_at)->diffForHumans() ?? '' }}</small></b>
                    @if ($val->session_id == $session_actual->id)
                        <div class="theme-pill theme-pill--success" style="width: 160px;"><i class="bi bi-circle-fill intermitente-device" style="font-size: 10px;"></i>&nbsp;<b> Dispositivo actual</b></div>
                    @elseif ($sessiones_usuario->where('id', $val->session_id)->count() == 1)
                        <div style="display: flex;">
                            <div class="theme-pill theme-pill--success" style="width: 160px; margin-right: 10px;"><i class="bi bi-circle-fill intermitente-device" style="font-size: 10px;"></i>&nbsp;<b> Sesión activa</b></div>
                            <form action="{{ route('perfil_cerrar_session', ['session_id' => $val->session_id]) }}" method="POST" class="d-inline-block" id="formD{{ $loop->iteration }}">
                                @csrf
                                @method('POST')
                                <button type="button" class="cerrar-sesion-boton" onclick="alerta('formD{{ $loop->iteration }}','¿Está seguro de cerrar la sesión en este dispositivo?', 'Si, cerrar')" data-bs-toggle="tooltip" title="Cerrar sesión">
                                    <b>Cerrar sesión</b>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="theme-pill theme-pill--danger" style="width: 140px;"><i class="bi bi-circle-fill" style="font-size: 10px;"></i>&nbsp;<b> Sesión cerrada</b></div>
                    @endif
                </div>
            </div>
        @endforeach<br>
    </div>
</div>
@endsection
