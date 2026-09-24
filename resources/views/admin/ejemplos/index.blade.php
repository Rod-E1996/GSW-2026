@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Lista de ejemplos' }}
@endsection

@section('content')
<div class="container">

    <div class="card p-3">
        <form method="GET" action="{{ url('ejemplo') }}" accept-charset="UTF-8" class="d-inline-block my-2 my-lg-0 float-end" role="search">

            <div class="input-group">

                <div class="col-md-6 col-12 p-1">
                    <label>Nombre: </label>
                    <input type="text" class="form-control border" name="nombre" placeholder="Buscar..." value="{{ request('nombre') }}">
                </div>

                <div class="col-md-6 col-12 p-1">
                    <label>Fecha inicio: </label>
                    <input type="date" class="form-control border" name="fecha" placeholder="Buscar..." value="{{ request('fecha') }}">
                </div>

            </div>

            <div class="input-group">

                <div class="col-md-6 col-12 p-1">
                    <label>Select Quemado: </label>
                    <select class="form-control select2" name="select_quemado" id="select_quemado" style="width: 100%;">
                        <option value="">Seleccione...</option>
                        <option value="1" {{ "1" == request('select_quemado') ? 'selected' : ''}}>Dato 1</option>
                        <option value="2" {{ "2" == request('select_quemado') ? 'selected' : ''}}>Dato 2</option>
                        <option value="3" {{ "3" == request('select_quemado') ? 'selected' : ''}}>Dato 3</option>
                    </select>
                </div>

                <div class="col-md-6 col-12 p-1">
                    <label>Auditar acciones: </label>
                    <select class="form-control select2" name="auditar_accion_id" id="auditar_accion_id" style="width: 100%;">
                        <option value="">Seleccione...</option>
                        @foreach($auditar_acciones as $value)
                            <option value="{{ $value->id }}" {{ $value->id == request('auditar_accion_id') ? 'selected' : ''}}>{{ $value->nombre }}</option>
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
            <form method="GET" action="{{ url('ejemplo') }}" accept-charset="UTF-8" class="" role="search">
                <div class="row">
                    <div class="col-md-12">
                        <a href="{{ url('ejemplo/create') }}" class="btn btn-secondary float-right my-2" data-bs-toggle="tooltip" title="Nuevo">
                            <i class="fas fa-plus"></i>&nbsp;Nuevo
                        </a>
                    </div>
                    {{-- <div class="col-md-6">
                        <div class="d-inline-block float-end my-2">
                            <div class="input-group">
                                <input type="text" class="form-control border" name="search" placeholder="Buscar..." value="{{ request('search') }}">
                                <span class="input-group-append">
                                    <button class="btn btn-info" type="submit" data-bs-toggle="tooltip" title="Buscar">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div> --}}
                </div>
            </form>
        </div>
        <!-- /.card-header -->
        <div class="card-body pt-3">
            <div class="table-responsive">
                <table class="table table-striped text-center border">
                    <thead class="text-uppercase">
                        <tr>
                            <th>Nombre</th>
                            <th>Telefono</th>
                            <th>Fecha</th>
                            <th>Cantidad</th>
                            <th>Catalogo Quemado</th>
                            <th>Catalogo BD</th>
                            <th>Estado</th>
                            <th>opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ejemplos as $value)
                        <tr>
                            <td>{{ $value->nombre ?? '' }}</td>
                            <td>{{ $value->telefono ?? '' }}</td>
                            <td>{{ \Carbon\Carbon::parse($value->fecha)->format('Y-m-d') }}</td>
                            <td>$ {{ $value->cantidad ?? '' }}</td>
                            <td>
                                <span class="badge bg-info">
                                    @if($value->select_quemado == 1)
                                        Dato 1
                                    @elseif($value->select_quemado == 2)
                                        Dato 2
                                    @else
                                        Dato 3
                                    @endif
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $value->auditarAccion->nombre }}</span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $value->estado == 1? 'success' : 'danger'}} text-white">{{ $value->estado == 1? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td>
                                <a href="{{ url('ejemplo/' . $value->id ) }}" class="btn my-sm btn-sm btn-secondary" data-bs-toggle="tooltip" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ url('ejemplo/' . $value->id . '/edit ') }}" class="btn my-sm btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ url('ejemplo/' . $value->id ) }}" method="POST" class="d-inline-block" id="formD{{ $loop->iteration }}">
                                    @csrf
                                    @method('POST')
                                    <button type="button" class="btn-sm my-md-1 btn btn-danger" onclick="alerta('formD{{ $loop->iteration }}','¿Está seguro de eliminar este registro, después de eliminarlo no habrá forma de recuperarlo?')" data-bs-toggle="tooltip" title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
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
                {!! $ejemplos->onEachSide(1)->appends([
                    'nombre' => Request::get('nombre'),
                    'fecha' => Request::get('fecha'),
                    'select_quemado' => Request::get('select_quemado'),
                    'auditar_accion_id' => Request::get('auditar_accion_id')
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
