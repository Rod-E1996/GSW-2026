@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Editar tipo de habitación' }}
@endsection

@section('content')
    <div class="container">
        <form method="POST" action="{{ url('tipo_habitacion/' . $tipo_habitacion->id ) }}" accept-charset="UTF-8" class="form-horizontal">
            @csrf
            @method('PUT')
            @include ('admin.tipos_habitacion.form', ['formMode' => 'Editar'])
        </form>
    </div>
@endsection
