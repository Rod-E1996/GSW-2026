{{-- Galeria de imagenes de un tipo de habitacion.
     $tipo_habitacion : modelo con la relacion imagenes cargada
     $editable        : true muestra los botones de principal / eliminar --}}
<div class="card mt-3">
    <div class="card-header">
        <strong><i class="fas fa-images"></i>&nbsp;Fotos ({{ $tipo_habitacion->imagenes->count() }})</strong>
        @if($editable)
            <small class="text-muted ms-2">La imagen marcada con la estrella es la que se muestra en el sitio público.</small>
        @endif
    </div>
    <div class="card-body">
        @if($tipo_habitacion->imagenes->isEmpty())
            <p class="text-muted mb-0">Este tipo de habitación aún no tiene fotos.</p>
        @else
            <div class="row g-3">
                @foreach($tipo_habitacion->imagenes as $imagen)
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card h-100 {{ $imagen->principal ? 'border-warning' : '' }}">
                            <a href="{{ $imagen->url }}" target="_blank" rel="noopener">
                                <img src="{{ $imagen->url }}" class="card-img-top" alt="{{ $tipo_habitacion->nombre }}" style="height: 160px; object-fit: cover;">
                            </a>
                            <div class="card-body p-2 text-center">
                                @if($imagen->principal)
                                    <span class="badge bg-warning text-dark"><i class="fas fa-star"></i> Principal</span>
                                @elseif($editable)
                                    @can('tipo_habitacion_update')
                                    <form action="{{ url('tipo_habitacion/' . $tipo_habitacion->id . '/imagen/' . $imagen->id . '/principal') }}" method="POST" class="d-inline-block">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Marcar como principal">
                                            <i class="far fa-star"></i>
                                        </button>
                                    </form>
                                    @endcan
                                @endif

                                @if($editable)
                                    @can('tipo_habitacion_update')
                                    <form action="{{ url('tipo_habitacion/' . $tipo_habitacion->id . '/imagen/' . $imagen->id . '/eliminar') }}" method="POST" class="d-inline-block" id="formImg{{ $imagen->id }}">
                                        @csrf
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="alerta('formImg{{ $imagen->id }}','¿Eliminar esta foto?')" data-bs-toggle="tooltip" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    @endcan
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
