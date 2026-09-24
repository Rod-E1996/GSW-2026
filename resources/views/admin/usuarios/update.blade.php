@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Datos del usuario' }}
@endsection

@section('content')
    <div class="container">
        <form method="POST" action="{{ url('usuario/' . $usuario->id ) }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include ('admin.usuarios.form', ['formMode' => 'Editar'])
        </form>
    <div>
@endsection
