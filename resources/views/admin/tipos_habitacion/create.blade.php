@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Nuevo tipo de habitación' }}
@endsection

@section('content')
    <div class="container">
        <form method="POST" action="{{ url('tipo_habitacion') }}" accept-charset="UTF-8" class="form-horizontal">
            @csrf
            @include ('admin.tipos_habitacion.form', ['formMode' => 'Crear'])
        </form>
    </div>
@endsection
