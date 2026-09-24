@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Datos del usuario' }}
@endsection

@section('content')
    <div class="container">
        <form method="POST" action="{{ url('usuario') }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
            @csrf
            @include ('admin.usuarios.form', ['formMode' => 'Crear'])
        </form>
    <div>
@endsection
