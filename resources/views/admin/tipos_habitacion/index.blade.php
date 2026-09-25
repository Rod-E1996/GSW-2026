@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Tipos de habitación' }}
@endsection

@section('content')
<div class="container">

    <div class="card p-3">
        <form method="GET" action="{{ url('tipo_habitacion') }}" accept-charset="UTF-8" class="d-inline-block my-2 my-lg-0 float-end" role="search">

            <div class="input-group">

                <div class="col-md-6 col-12 p-1">
                    <label>Nombre: </label>
                    <input type="text" class="form-control border" name="nombre" placeholder="Buscar..." value="{{ request('nombre') }}">
                </div>

                <div class="col-md-6 col-12 p-1">
                    <label>Capacidad mínima: </label>
                    <input type="number" min="1" class="form-control border" name="capacidad" placeholder="Huéspedes" value="{{ request('capacidad') }}">
                </div>

            </div>

            <div class="input-group">

                <div class="col-md-6 p-1 mt-4" >
                    <button class="btn btn-info" type="submit">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>

            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col-md-12">
                    @can('tipo_habitacion_create')
                    <a href="{{ url('tipo_habitacion/create') }}" class="btn btn-secondary float-right my-2" data-bs-toggle="tooltip" title="Nuevo">
                        <i class="fas fa-plus"></i>&nbsp;Nuevo
                    </a>
                    @endcan
                </div>
            </div>
        </div>
        <!-- /.card-header -->
        <div class="card-body pt-3">
            <div class="table-responsive">
                <table class="table table-striped text-center border">
                    <thead class="text-uppercase">
                        <tr>
                            <th>Foto</th>
                            <th>Nombre</th>
                            <th>Capacidad</th>
                            <th>Precio base</th>
                            <th>Estado</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tipos_habitacion as $value)
                        <tr>
                            <td>
                                @if($value->imagenPrincipal)
                                    <img src="{{ $value->imagenPrincipal->url }}" alt="{{ $value->nombre }}" class="rounded" style="width: 72px; height: 48px; object-fit: cover;">
                                @else
                                    <span class="text-muted" data-bs-toggle="tooltip" title="Sin foto"><i class="far fa-image fa-2x"></i></span>
                                @endif
                            </td>
                            <td>{{ $value->nombre ?? '' }}</td>
                            <td>
                                <span class="badge bg-info text-white">
                                    <i class="fas fa-user"></i> {{ $value->capacidad }} {{ $value->capacidad == 1 ? 'huésped' : 'huéspedes' }}
                                </span>
                            </td>
                            <td>$ {{ number_format($value->precio_base, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $value->estado == 1? 'success' : 'danger'}} text-white">{{ $value->estado == 1? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td>
                                @can('tipo_habitacion_show')
                                <a href="{{ url('tipo_habitacion/' . $value->id ) }}" class="btn my-sm btn-sm btn-secondary" data-bs-toggle="tooltip" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @endcan
                                @can('tipo_habitacion_edit')
                                <a href="{{ url('tipo_habitacion/' . $value->id . '/edit') }}" class="btn my-sm btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endcan
                                @can('tipo_habitacion_destroy')
                                <form action="{{ url('tipo_habitacion/' . $value->id ) }}" method="POST" class="d-inline-block" id="formD{{ $loop->iteration }}">
                                    @csrf
                                    @method('POST')
                                    <button type="button" class="btn-sm my-md-1 btn btn-danger" onclick="alerta('formD{{ $loop->iteration }}','¿Está seguro de eliminar este tipo de habitación?')" data-bs-toggle="tooltip" title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-muted">No hay tipos de habitación registrados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <!-- /.card-body -->
        <div class="card-footer clearfix text-center">
            <label>
                {!! $tipos_habitacion->onEachSide(1)->appends([
                    'nombre' => Request::get('nombre'),
                    'capacidad' => Request::get('capacidad')
                ])->render() !!}
            </label>
        </div>

    </div>
</div>
@endsection
