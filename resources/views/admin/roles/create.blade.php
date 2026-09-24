@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Datos del rol' }}
@endsection

@section('content')
    <div class="container">
        <form method="POST" action="{{ url('role') }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
            @csrf
            @include ('admin.roles.form', ['formMode' => 'Crear'])
        </form>
    <div>
@endsection
