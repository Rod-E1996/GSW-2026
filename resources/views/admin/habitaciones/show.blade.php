@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Habitación ' . $habitacion->numero }}
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <a href="{{ url('habitacion') }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Atrás">
                <i class="fas fa-arrow-left"></i>&nbsp;Atrás
            </a>
            @can('habitacion_edit')
            <a href="{{ url('habitacion/' . $habitacion->id . '/edit') }}" class="btn btn-secondary" data-bs-toggle="tooltip" title="Editar">
                <i class="fas fa-edit"></i> &nbsp;Editar
            </a>
            @endcan
        </div>

        <div class="card-body">
            <table class="table table-bordered text-center">
                <tbody>
                    <tr>
                        <th>NÚMERO</th>
                        <td>{{ $habitacion->numero }}</td>
                    </tr>
                    <tr>
                        <th>PISO</th>
                        <td>{{ $habitacion->piso }}</td>
                    </tr>
                    <tr>
                        <th>TIPO DE HABITACIÓN</th>
                        <td>
                            @can('tipo_habitacion_show')
                                <a href="{{ url('tipo_habitacion/' . $habitacion->tipo_habitacion_id) }}">{{ $habitacion->tipoHabitacion->nombre ?? '' }}</a>
                            @else
                                {{ $habitacion->tipoHabitacion->nombre ?? '' }}
                            @endcan
                        </td>
                    </tr>
                    <tr>
                        <th>CAPACIDAD</th>
                        <td>{{ $habitacion->tipoHabitacion->capacidad ?? '' }} {{ ($habitacion->tipoHabitacion->capacidad ?? 0) == 1 ? 'huésped' : 'huéspedes' }}</td>
                    </tr>
                    <tr>
                        <th>PRECIO BASE POR NOCHE</th>
                        <td>$ {{ number_format($habitacion->tipoHabitacion->precio_base ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <th>ESTADO</th>
                        <td>
                            <span class="badge bg-{{ $habitacion->estado_color }} text-white">{{ $habitacion->estado_nombre }}</span>
                            @can('habitacion_estado')
                            <form action="{{ url('habitacion/estado/' . $habitacion->id) }}" method="POST" class="d-inline-block ms-3">
                                @csrf
                                <div class="input-group input-group-sm">
                                    <select name="estado_habitacion" class="form-select form-select-sm">
                                        @foreach($estados as $valor => $estado)
                                            <option value="{{ $valor }}" {{ $valor == $habitacion->estado_habitacion ? 'selected' : '' }}>{{ $estado['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-primary btn-sm" type="submit" data-bs-toggle="tooltip" title="Cambiar estado">
                                        <i class="fas fa-sync-alt"></i> Cambiar
                                    </button>
                                </div>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    <tr>
                        <th>DESCRIPCIÓN</th>
                        <td>{{ $habitacion->descripcion ?? 'Sin descripción' }}</td>
                    </tr>
                    <tr>
                        <th>CREADO</th>
                        <td>{{ $habitacion->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th>ÚLTIMA MODIFICACIÓN</th>
                        <td>{{ $habitacion->updated_at->format('d/m/Y H:i') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
  </div>
</div>
@endsection
