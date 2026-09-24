@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Lista de Auditoría' }}
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ url('auditar') }}" accept-charset="UTF-8" class="d-inline-block my-2 my-lg-0 float-end" role="search">
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
        <!-- /.card-header -->
        <div class="card-body pt-3">
            <div class="table-responsive">
                <table class="table table-striped text-center border">
                    <thead class="text-uppercase">
                        <tr>
                            <th>Usuario</th>
                            <th>Accion</th>
                            <th>Entidad</th>
                            <th>Nombre Entidad<br>Modificada</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auditar as $value)
                        <tr>
                            <td>{{ $value->user->name ?? '' }}</td>
                            <td>{{ $value->auditarAccion->nombre ?? '' }}</td>
                            <td>{{ $value->auditarTabla->tabla ?? '' }}</td>
                            <td>{{ $value->nombre_modificado ?? '' }}</td>
                            <td>{{ \Carbon\Carbon::parse($value->created_at)->format('Y-m-d') }}</td>
                            <td>{{ \Carbon\Carbon::parse($value->created_at)->format('h:i:s A') }}</td>
                            <td>
                                <a href="{{ url('auditar/' . $value->id ) }}" class="btn btn-secondary" data-bs-toggle="tooltip" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
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
                {!! $auditar->onEachSide(1)->appends(['search' => Request::get('search')])->render() !!}
            </label>
        </div>
    </div>
</div>
@endsection
