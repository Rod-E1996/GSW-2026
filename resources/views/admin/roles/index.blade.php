@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Lista de roles' }}
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ url('role') }}" accept-charset="UTF-8" role="search">
                <div class="row">
                    <div class="col">
                    <a href="{{ url('role/create') }}" class="btn btn-secondary float-right">
                        <i class="fas fa-plus"></i>&nbsp;Nuevo
                    </a>
                    </div>
                    <div class="col">
                    <div class="d-inline-block float-end">
                        <div class="input-group ">
                        <input type="text" class="form-control border" name="search" placeholder="Buscar..." value="{{ request('search') }}">
                        <span class="input-group-append">
                            <button class="btn btn-info" type="submit">
                            <i class="fas fa-search"></i>
                            </button>
                        </span>
                        </div>
                    </div>
                    </div>
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
                            <th>Permisos</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $value)
                        <tr>
                            <td>{{ $value->name ?? '' }}</td>
                            <td>
                                <a href="{{ url('role/' . $value->id) }}">
                                    <span class="badge badge-primary text-white bg-primary">{{ $value->permissions->count() }}</span>
                                </a>
                            </td>
                            <td>{{ \Carbon\Carbon::parse($value->created_at)->format('Y-m-d') }}</td>
                            <td>{{ \Carbon\Carbon::parse($value->created_at)->format('h:i:s A') }}</td>
                            <td>
                                <a href="{{ url('role/' . $value->id ) }}" class="btn my-sm btn-sm btn-secondary" data-bs-toggle="tooltip" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ url('role/' . $value->id . '/edit ') }}" class="btn my-sm btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ url('role/' . $value->id ) }}" method="POST" class="d-inline-block" id="formD{{ $loop->iteration }}">
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
                {!! $roles->onEachSide(1)->appends(['search' => Request::get('search')])->render() !!}
            </label>
        </div>
    </div>
</div>
@endsection
