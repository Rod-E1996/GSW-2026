@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Editar habitación ' . $habitacion->numero }}
@endsection

@section('content')
    <div class="container">
        <form method="POST" action="{{ url('habitacion/' . $habitacion->id ) }}" accept-charset="UTF-8" class="form-horizontal">
            @csrf
            @method('PUT')
            @include ('admin.habitaciones.form', ['formMode' => 'Editar'])
        </form>
    </div>
@endsection
