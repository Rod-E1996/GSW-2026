@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Nueva habitación' }}
@endsection

@section('content')
    <div class="container">
        <form method="POST" action="{{ url('habitacion') }}" accept-charset="UTF-8" class="form-horizontal">
            @csrf
            @include ('admin.habitaciones.form', ['formMode' => 'Crear'])
        </form>
    </div>
@endsection
