@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Ver Auditoría' }}
@endsection

@section('content')
<script src="{{asset('plugins/jquery-jsonview/jquery.jsonview.js')}}"></script>
<link rel="stylesheet" href="{{asset('plugins/jquery-jsonview/jquery.jsonview.css')}}">

<div class="container">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title d-inline-block">
                <a href="{{ url('auditar') }}" class="btn btn-danger" title="Atrás">
                    <i class="fas fa-arrow-left"></i>&nbsp;Atrás
                </a>
            </h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <table class="table table-bordered text-center">
                <tbody>
                    <tr>
                        <th>ACCIÓN</th>
                        <td>{{ $auditar->auditarAccion->nombre ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>ENTIDAD</th>
                        <td>{{ $auditar->auditarTabla->tabla ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>NOMBRE ENTIDAD MODIFICADA</th>
                        <td>{{ $auditar->nombre_modificado ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>REALIZADO POR</th>
                        <td>{{ $auditar->user->name ?? $auditar->name ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>EMAIL</th>
                        <td>{{ $auditar->user->email ?? $auditar->email ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>FECHA</th>
                        <td>{{ \Carbon\Carbon::parse($auditar->created_at)->format('Y-m-d') ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>HORA</th>
                        <td>{{ \Carbon\Carbon::parse($auditar->created_at)->format('h:i:s A') ?? '' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header pagetitle"><h1>Datos</h1></div>
        <div class="card-body">
            <div class="accordion accordion-flush" id="accordion">
                @if( !empty($auditar->antes) )
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="antes">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAntes" aria-expanded="false" aria-controls="collapseAntes">
                                Antes
                            </button>
                        </h2>
                        <div id="collapseAntes" class="accordion-collapse collapse" aria-labelledby="antes" data-bs-parent="#accordion">
                            <div class="accordion-body">
                                <div class="container-json">
                                    <pre class="p-3" id="json-antes"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        $(document).ready(function () {
                            var json_aux_antes = '{{  str_replace( '\"', '´', $auditar->antes )  }}';
                            json_aux_antes = json_aux_antes.replace(/&quot;/gi, '"')
                            $("#json-antes").JSONView(json_aux_antes, { collapsed: true });
                        });
                    </script>
                @endif

                @if( !empty($auditar->despues) )
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="despues">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDespues" aria-expanded="false" aria-controls="collapseDespues">
                                Después
                            </button>
                        </h2>
                        <div id="collapseDespues" class="accordion-collapse collapse" aria-labelledby="despues" data-bs-parent="#accordion">
                            <div class="accordion-body">
                                <div class="container-json">
                                    <pre class="p-3" id="json-despues"></pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        $(document).ready(function () {
                            var json_aux_despues = '{{  str_replace( '\"', '´', $auditar->despues )  }}';
                            json_aux_despues = json_aux_despues.replace(/&quot;/gi, '"')
                            $("#json-despues").JSONView(json_aux_despues, { collapsed: true });
                        });
                    </script>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
