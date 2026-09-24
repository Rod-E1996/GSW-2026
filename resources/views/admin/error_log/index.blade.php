@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Lista de Errores' }}
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col">
                    <form action="{{ url('error_log/crearError/1') }}" method="GET" class="d-inline-block" id="formD">
                        @csrf
                        @method('GET')
                        <button type="button" class="btn btn-danger my-2" onclick="alerta('formD', '¿Está seguro de crear un nuevo registro de prueba en el error log?', 'Sí, crear')" data-bs-toggle="tooltip" title="Crear un registro de prueba en el error log">
                            <i class="fas fa-plus"></i>&nbsp;Registro de prueba
                        </button>
                    </form>
                </div>
                <div class="col">
                    <div class="d-inline-block float-end my-2">
                        <form method="GET" action="{{ url('error_log') }}" accept-charset="UTF-8" class="" role="search">
                            <div class="input-group">
                                <input type="text" class="form-control border" name="search" placeholder="Buscar..." value="{{ request('search') }}">
                                <span class="input-group-append">
                                    <button class="btn btn-info" type="submit" title="Buscar">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.card-header -->
        <div class="card-body pt-3">
            <div class="table-responsive">
                <table class="table table-striped text-center border">
                    <thead class="text-uppercase">
                        <tr>
                            <th>Usuario</th>
                            <th>Controller</th>
                            <th>Mensaje</th>
                            <th>Parametros</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($error_log as $value)
                            <tr>
                                <td><span class="badge bg-{{ $value->user ? 'info' : 'danger'}} text-white">{{ $value->user->name ?? '(Sin usuario)' }}</span></td>
                                <td>{{ $value->controller ?? '' }}</td>
                                <td>{{ $value->mensaje ?? '' }}</td>
                                <td>{{ $value->parametros ?? '' }}</td>
                                <td>{{ \Carbon\Carbon::parse($value->created_at)->format('Y-m-d') }}</td>
                                <td>{{ \Carbon\Carbon::parse($value->created_at)->format('h:i:s A') }}</td>
                                <td>
                                    <a href="{{ url('error_log/' . $value->id ) }}" class="btn-sm my-md-1 btn btn-secondary" data-bs-toggle="tooltip" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($value->estado == 0)
                                        <form action="{{ url('error_log/estado/' . $value->id ) }}" method="POST" class="d-inline-block" id="formVerificar{{ $loop->iteration }}">
                                            @csrf
                                            <button type="button" class="btn-sm my-md-1 btn btn-success" onclick="alerta('formVerificar{{ $loop->iteration }}','¿Está seguro de marcar este error como resuelto?', 'Sí, resuelto')" data-bs-toggle="tooltip" title="Marcar como resuelto">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <!-- /.card-body -->
        <div class="card-footer clearfix text-center">
            <label>
                {!! $error_log->onEachSide(1)->appends(['search' => Request::get('search')])->render() !!}
            </label>
        </div>
    </div>
</div>
@endsection
