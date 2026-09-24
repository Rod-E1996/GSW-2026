@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Detalles de usuario: '. $usuario->name }}
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <a href="{{ url('usuario') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
                <i class="fas fa-arrow-left"></i>&nbsp;Atrás
            </a>
            <a href="{{ url('usuario/' . $usuario->id . '/edit ') }}" class="btn btn-secondary" data-bs-toggle="tooltip" title="Editar">
                <i class="fas fa-edit"></i> &nbsp;Editar
            </a>
            @can('usuario_sessiones')
                <a href="{{ url('usuario/' . $usuario->id . '/sessiones') }}" class="btn btn-info" data-bs-toggle="tooltip" title="Ver las sesiones abiertas de este usuario">
                    <i class="fas fa-laptop"></i> &nbsp;Sesiones
                </a>
            @endcan
            <form action="{{ url('usuario/estado/' . $usuario->id ) }}" method="POST" class="d-inline-block py-1" >
                @csrf
                @if( $usuario->estado != 1 )
                    <input type="hidden" name="estado" value="1">
                    <button type="submit" class="btn btn-success" data-bs-toggle="tooltip" title="Activar">ACTIVAR</button>
                @else
                    <input type="hidden" name="estado" value="0">
                    <button type="submit" class="btn btn-danger" data-bs-toggle="tooltip" title="Desactivar">DESACTIVAR</button>
                @endif
            </form>
        </div>

        <div class="card-body">
            <table class="table table-bordered text-center">
                <tbody>
                    <tr>
                        <th>NOMBRE</th>
                        <td>{{ $usuario->name }}</td>
                    </tr>
                    <tr>
                        <th>CORREO</th>
                        <td>{{ $usuario->email }}</td>
                    </tr>
                    <tr>
                        <th>ROLES</th>
                        <td>
                            @foreach($usuario->getRoleNames() as $rol)
                                <span class="badge bg-info">{{ $rol }}</span>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <th>ESTADO</th>
                        <td>
                            @if( $usuario->estado == 1 )
                                <span class="badge bg-success text-white">ACTIVO</span>
                            @else
                                <span class="badge bg-danger text-white">INACTIVO</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
  </div>
</div>
@endsection
