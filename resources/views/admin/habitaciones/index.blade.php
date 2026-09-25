@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Habitaciones' }}
@endsection

@section('content')
<div class="container">

    <div class="card p-3">
        <form method="GET" action="{{ url('habitacion') }}" accept-charset="UTF-8" class="d-inline-block my-2 my-lg-0 float-end" role="search">

            <div class="input-group">

                <div class="col-md-4 col-12 p-1">
                    <label>Número: </label>
                    <input type="text" class="form-control border" name="numero" placeholder="Buscar..." value="{{ request('numero') }}">
                </div>

                <div class="col-md-4 col-12 p-1">
                    <label>Tipo de habitación: </label>
                    <select class="form-control select2" name="tipo_habitacion_id" id="tipo_habitacion_id" style="width: 100%;">
                        <option value="">Todos</option>
                        @foreach($tipos_habitacion as $value)
                            <option value="{{ $value->id }}" {{ $value->id == request('tipo_habitacion_id') ? 'selected' : ''}}>{{ $value->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-12 p-1">
                    <label>Estado: </label>
                    <select class="form-control select2" name="estado_habitacion" id="estado_habitacion" style="width: 100%;">
                        <option value="">Todos</option>
                        @foreach($estados as $valor => $estado)
                            <option value="{{ $valor }}" {{ $valor == request('estado_habitacion') ? 'selected' : ''}}>{{ $estado['nombre'] }}</option>
                        @endforeach
                    </select>
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
                    @can('habitacion_create')
                    <a href="{{ url('habitacion/create') }}" class="btn btn-secondary float-right my-2" data-bs-toggle="tooltip" title="Nuevo">
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
                            <th>Número</th>
                            <th>Piso</th>
                            <th>Tipo</th>
                            <th>Capacidad</th>
                            <th>Precio base</th>
                            <th>Estado</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($habitaciones as $value)
                        <tr>
                            <td><strong>{{ $value->numero }}</strong></td>
                            <td>{{ $value->piso }}</td>
                            <td>{{ $value->tipoHabitacion->nombre ?? '' }}</td>
                            <td>
                                <span class="badge bg-info text-white">
                                    <i class="fas fa-user"></i> {{ $value->tipoHabitacion->capacidad ?? '' }}
                                </span>
                            </td>
                            <td>$ {{ number_format($value->tipoHabitacion->precio_base ?? 0, 2) }}</td>
                            <td>
                                @can('habitacion_estado')
                                <form action="{{ url('habitacion/estado/' . $value->id) }}" method="POST" class="d-inline-block">
                                    @csrf
                                    <select name="estado_habitacion" class="form-select form-select-sm border-{{ $value->estado_color }}" onchange="this.form.submit()" data-bs-toggle="tooltip" title="Cambiar estado">
                                        @foreach($estados as $valor => $estado)
                                            <option value="{{ $valor }}" {{ $valor == $value->estado_habitacion ? 'selected' : '' }}>{{ $estado['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                @else
                                <span class="badge bg-{{ $value->estado_color }} text-white">{{ $value->estado_nombre }}</span>
                                @endcan
                            </td>
                            <td>
                                @can('habitacion_show')
                                <a href="{{ url('habitacion/' . $value->id ) }}" class="btn my-sm btn-sm btn-secondary" data-bs-toggle="tooltip" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @endcan
                                @can('habitacion_edit')
                                <a href="{{ url('habitacion/' . $value->id . '/edit') }}" class="btn my-sm btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endcan
                                @can('habitacion_destroy')
                                <form action="{{ url('habitacion/' . $value->id ) }}" method="POST" class="d-inline-block" id="formD{{ $loop->iteration }}">
                                    @csrf
                                    @method('POST')
                                    <button type="button" class="btn-sm my-md-1 btn btn-danger" onclick="alerta('formD{{ $loop->iteration }}','¿Está seguro de eliminar la habitación {{ $value->numero }}?')" data-bs-toggle="tooltip" title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-muted">No hay habitaciones registradas.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <!-- /.card-body -->
        <div class="card-footer clearfix text-center">
            <label>
                {!! $habitaciones->onEachSide(1)->appends([
                    'numero' => Request::get('numero'),
                    'tipo_habitacion_id' => Request::get('tipo_habitacion_id'),
                    'estado_habitacion' => Request::get('estado_habitacion')
                ])->render() !!}
            </label>
        </div>

    </div>
</div>

<script type="text/javascript">
	$(document).ready(function() {

        $('.select2').select2({ theme: 'bootstrap4', width: '100%', dropdownAutoWidth: true });

        $(document).on('select2:open', () => {
            document.querySelector('.select2-search__field').focus();
        });

    });
</script>

@endsection
