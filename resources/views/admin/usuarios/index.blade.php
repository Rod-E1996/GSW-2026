@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Lista de usuarios' }}
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ url('usuario') }}" accept-charset="UTF-8" class="" role="search">
                <div class="row">
                    <div class="col">
                        <a href="{{ url('usuario/create') }}" class="btn btn-secondary float-right my-2" data-bs-toggle="tooltip" title="Nuevo">
                            <i class="fas fa-plus"></i>&nbsp;Nuevo
                        </a>
                        @can('session_index')
                            <a href="{{ url('usuario/sessiones') }}" class="btn btn-primary float-right my-2 ms-2 position-relative" data-bs-toggle="tooltip" title="Ver las sesiones abiertas de todos los usuarios">
                                <i class="fas fa-satellite-dish"></i>&nbsp;Sesiones activas
                                @if(!empty($total_sesiones_activas))
                                    <span class="ses-count">{{ $total_sesiones_activas }}</span>
                                @endif
                            </a>
                        @endcan
                    </div>
                    <div class="col">
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
                            <th>Correo</th>
                            <th>Roles</th>
                            <th>Registro</th>
                            <th>Estado</th>
                            <th>opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $value)
                        <tr>
                            <td>{{ $value->name ?? '' }}</td>
                            <td>{{ $value->email ?? '' }}</td>
                            <td>
                                @foreach($value->getRoleNames() as $rol)
                                    <span class="badge bg-info">{{ $rol }}</span>
                                @endforeach
                            </td>
                            <td>{{ \Carbon\Carbon::parse($value->created_at)->format('Y-m-d h:i:s A') }}</td>
                            <td>
                                <form action="{{ url('usuario/estado/' . $value->id ) }}" method="POST" class="d-inline-block py-1"
                                    id="formE{{ $loop->iteration }}" >
                                @csrf
                                    <input type="checkbox" data-toggle="toggle" data-on="Activo" data-off="Inactivo"
                                    data-onstyle="success" data-offstyle="danger"  name="estado" value="1" {{ ($value->estado != 1)?'':'checked' }} onchange="$('#formE{{ $loop->iteration }}').submit();">
                                </form>
                            </td>
                            <td>
                                <a href="{{ url('usuario/' . $value->id ) }}" class="btn my-sm btn-sm btn-secondary" data-bs-toggle="tooltip" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ url('usuario/' . $value->id . '/edit ') }}" class="btn my-sm btn-sm btn-primary" data-bs-toggle="tooltip" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @can('usuario_sessiones')
                                    @php $activas = $sesiones_por_usuario[$value->id] ?? 0; @endphp
                                    <a href="{{ url('usuario/' . $value->id . '/sessiones') }}" class="btn my-sm btn-sm btn-info position-relative" data-bs-toggle="tooltip"
                                        title="{{ $activas > 0 ? "Sesiones abiertas: $activas" : 'Sin sesiones abiertas' }}">
                                        <i class="fas fa-laptop"></i>
                                        @if($activas > 0)
                                            <span class="ses-count">{{ $activas }}</span>
                                        @endif
                                    </a>
                                @endcan
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
                {!! $usuarios->onEachSide(1)->appends(['search' => Request::get('search')])->render() !!}
            </label>
        </div>
    </div>
</div>
@endsection
