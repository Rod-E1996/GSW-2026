@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Tipo de habitación: '. $tipo_habitacion->nombre }}
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <a href="{{ url('tipo_habitacion') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
                <i class="fas fa-arrow-left"></i>&nbsp;Atrás
            </a>
            @can('tipo_habitacion_edit')
            <a href="{{ url('tipo_habitacion/' . $tipo_habitacion->id . '/edit') }}" class="btn btn-secondary" data-bs-toggle="tooltip" title="Editar">
                <i class="fas fa-edit"></i> &nbsp;Editar
            </a>
            @endcan
        </div>

        <div class="card-body">
            <table class="table table-bordered text-center">
                <tbody>
                    <tr>
                        <th>NOMBRE</th>
                        <td>{{ $tipo_habitacion->nombre }}</td>
                    </tr>
                    <tr>
                        <th>CAPACIDAD</th>
                        <td>{{ $tipo_habitacion->capacidad }} {{ $tipo_habitacion->capacidad == 1 ? 'huésped' : 'huéspedes' }}</td>
                    </tr>
                    <tr>
                        <th>PRECIO BASE POR NOCHE</th>
                        <td>$ {{ number_format($tipo_habitacion->precio_base, 2) }}</td>
                    </tr>
                    <tr>
                        <th>ESTADO</th>
                        <td><span class="badge bg-{{ $tipo_habitacion->estado == 1? 'success' : 'danger'}} text-white">{{ $tipo_habitacion->estado == 1? 'Activo' : 'Inactivo' }}</span></td>
                    </tr>
                    <tr>
                        <th>DESCRIPCIÓN</th>
                        <td>{{ $tipo_habitacion->descripcion ?? 'Sin descripción' }}</td>
                    </tr>
                    <tr>
                        <th>CREADO</th>
                        <td>{{ $tipo_habitacion->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th>ÚLTIMA MODIFICACIÓN</th>
                        <td>{{ $tipo_habitacion->updated_at->format('d/m/Y H:i') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @include ('admin.tipos_habitacion.imagenes', ['editable' => false])
</div>
@endsection
