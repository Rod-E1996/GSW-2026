@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Ver Error' }}
@endsection

@section('content')
<script src="{{asset('plugins/jquery-jsonview/jquery.jsonview.js')}}"></script>
<link rel="stylesheet" href="{{asset('plugins/jquery-jsonview/jquery.jsonview.css')}}">

<div class="container">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title d-inline-block">
                <a href="{{ url('error_log') }}" class="btn btn-danger" title="Atrás">
                    <i class="fas fa-arrow-left"></i>&nbsp;Atrás
                </a>
            </h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <table class="table table-bordered text-center">
                <tbody>
                    <tr>
                        <th>USUARIO</th>
                        <td><span class="badge bg-{{ $error_log->user ? 'info' : 'danger'}} text-white">{{ $error_log->user->name ?? '(Sin usuario)' }}</span></td>
                    </tr>
                    <tr>
                        <th>CONTROLLER</th>
                        <td>{{ $error_log->controller ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>MENSAJE</th>
                        <td>{{ $error_log->mensaje ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>PARAMETROS</th>
                        <td>{{ $error_log->parametros ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>FECHA</th>
                        <td>{{ \Carbon\Carbon::parse($error_log->created_at)->format('Y-m-d') ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>HORA</th>
                        <td>{{ \Carbon\Carbon::parse($error_log->created_at)->format('h:i:s A') ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>ESTADO</th>
                        <td><span class="badge bg-{{ $error_log->estado == 0 ? 'danger' : 'success'}} text-white">{{ $error_log->estado == 0 ? 'Sin resolver' : 'Resuleto'}}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>



    <div class="card">
        <div class="card-body">
            <div class="card-header pagetitle"><h1>Parametros</h1></div>

            @if( !empty($error_log->parametros) )

                <div class="accordion" id="accordionExample">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                Parametros
                            </button>
                        </h2>

                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionExample" style="">
                            <div class="accordion-body">
                                <div class="container-json">
                                    <pre class="p-3" id="json-show-text"></pre>
                                </div>
                            </div>
                        </div>

                        <script>
                            $(document).ready(function () {
                                var json_text = '{{  str_replace( '\"', '´', $error_log->parametros )  }}';
                                json_text = json_text.replace(/&quot;/gi, '"')
                                $("#json-show-text").JSONView(json_text, { collapsed: true });
                            });
                        </script>

                    </div>
                </div>

            @endif

        </div>
    </div>

</div>
@endsection
