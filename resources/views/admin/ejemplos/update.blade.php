@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Datos del ejemplo' }}
@endsection

@section('content')
    <div class="container">
        <form method="POST" action="{{ url('ejemplo/' . $ejemplo->id ) }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include ('admin.ejemplos.form', ['formMode' => 'Editar'])
        </form>
    <div>
@endsection
