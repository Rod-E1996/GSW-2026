@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Lista de procesos en segundo plano' }}
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ url('queue_control') }}" accept-charset="UTF-8" class="d-inline-block my-2 my-lg-0 float-end" role="search">
                <div class="input-group">
                    <input type="text" class="form-control border" name="search" placeholder="Buscar..." value="{{ request('search') }}">
                    <span class="input-group-append">
                        <button class="btn btn-info" type="submit" title="Buscar">
                            <i class="fas fa-search"></i>
                        </button>
                    </span>
                </div>
            </form>
        </div>
        <!-- /.card-header -->
        <div class="card-body pt-3 mx-2">
            @foreach($queue_controls as $value)

                @php
                    if($value->pendiente == 0){
                        $class = 'progress-bar progress-bar-striped bg-primary';
                    }else{
                        $class = 'progress-bar progress-bar-striped bg-success progress-bar-animated pendiente';
                    }

                    $progreso = ($value->progreso / $value->total_procesos ) * 100;
                @endphp

                <div class="row border-div-segundo-plano py-3">

                    <div class="col-md-12">
                        <h6>{{ $value->titulo ?? '' }} <b>(Fecha de inicio: {{ \Carbon\Carbon::parse($value->fecha_inicio)->format('Y-m-d h:i:s A') }})</b> @if($value->pendiente == 1) <i class="ri ri-dvd-fill intermitente" id="icono-{{$value->id}}"></i> @else <i class="ri ri-checkbox-circle-fill no-intermitente"></i> @endif </h6>
                        <h6><b>Progreso: <span id="porcentaje-{{$value->id}}">{{$progreso}}%</span> - Total procesos: <span id="progreso-{{$value->id}}">{{$value->progreso}}</span> / {{$value->total_procesos}}</b></h6>
                        <div class="progress">
                            <div id="{{$value->id}}" data-start="0" data-finish="0" class="{{$class}}" role="progressbar"
                                aria-valuenow="{{$progreso}}" aria-valuemin="0" aria-valuemax="100" style="width:{{$progreso}}%">
                                {{$progreso}}%
                            </div>
                        </div>
                    </div>

                </div>

                <br>
            @endforeach
        </div>
        <!-- /.card-body -->
        <div class="card-footer clearfix text-center">
            <label>
                {!! $queue_controls->onEachSide(1)->appends(['search' => Request::get('search')])->render() !!}
            </label>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        setInterval(updatePorcentaje, 1000); // Llama a la función cada segundos
    });

    function updatePorcentaje() {

        $(".pendiente").each(function (index, value){

            let id = $(value).attr("id");

            $.ajax({
                type: 'GET',
                url: "{{ route('queue_control_update_porcentaje') }}",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: id },
                success: function(response) {

                    if(response.pendiente == 0){
                        //Cambiar aspecto de la barra de avance
                        $(value).removeClass("bg-success");
                        $(value).removeClass("progress-bar-animated");
                        $(value).removeClass("pendiente");
                        $(value).addClass("bg-primary");

                        //Cambiar de icono
                        $("#icono-" + id).removeClass("intermitente");
                        $("#icono-" + id).removeClass("ri-dvd-fill");
                        $("#icono-" + id).addClass("no-intermitente");
                        $("#icono-" + id).addClass("ri-checkbox-circle-fill");
                    }

                    //Cambiar los datos de avance
                    $('#' + id).attr('aria-valuenow', response.porcentaje);
                    $('#' + id).css('width', response.porcentaje + '%');
                    $('#' + id).text(response.porcentaje + '%');
                    $('#porcentaje-' + id).text(response.porcentaje + '%');
                    $('#progreso-' + id).text(response.progreso);

                },
                error: function(response) {
                    toastr.error('Ha ocurrido un error')
                }
            });
        });
    }
</script>

@endsection
