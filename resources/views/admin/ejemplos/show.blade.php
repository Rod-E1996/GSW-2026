@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Detalles del ejemplo: '. $ejemplo->nombre }}
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <a href="{{ url('ejemplo') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
                <i class="fas fa-arrow-left"></i>&nbsp;Atrás
            </a>
            <a href="{{ url('ejemplo/' . $ejemplo->id . '/edit ') }}" class="btn btn-secondary" data-bs-toggle="tooltip" title="Editar">
                <i class="fas fa-edit"></i> &nbsp;Editar
            </a>
        </div>

        <div class="card-body">
            <table class="table table-bordered text-center">
                <tbody>
                    <tr>
                        <th>NOMBRE</th>
                        <td>{{ $ejemplo->nombre }}</td>
                    </tr>
                    <tr>
                        <th>TELEFONO</th>
                        <td>{{ $ejemplo->telefono }}</td>
                    </tr>
                    <tr>
                        <th>FECHA</th>
                        <td>{{ $ejemplo->fecha }}</td>
                    </tr>
                    <tr>
                        <th>CANTIDAD</th>
                        <td>${{ $ejemplo->cantidad }}</td>
                    </tr>
                    <tr>
                        <th>PORCENTAJE</th>
                        <td>{{ $ejemplo->porcentaje }}%</td>
                    </tr>
                    <tr>
                        <th>CATALOGO QUEMADO</th>
                        <td>
                            <span class="badge bg-info">
                                @if($ejemplo->select_quemado == 1)
                                    Dato 1
                                @elseif($ejemplo->select_quemado == 2)
                                    Dato 2
                                @else
                                    Dato 3
                                @endif
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>CATALOGO BD</th>
                        <td><span class="badge bg-info">{{ $ejemplo->auditarAccion->nombre }}</span></td>
                    </tr>
                    <tr>
                        <th>ESTADO</th>
                        <td><span class="badge bg-{{ $ejemplo->estado == 1? 'success' : 'danger'}} text-white">{{ $ejemplo->estado == 1? 'Activo' : 'Inactivo' }}</span></td>
                    </tr>
                    <tr>
                        <th>DESCRIPCION</th>
                        <td>{{ $ejemplo->descripcion }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
  </div>
</div>
@endsection
