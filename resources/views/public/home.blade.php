@extends('layouts.public.app')

@section('title')
  {{ $hotel['nombre'] }} | Habitaciones y reservaciones
@endsection

@section('content')

{{-- ======= Hero ======= --}}
@php $portada = $hotel['galeria'][0]['archivo'] ?? null; @endphp
<section class="site-hero" id="inicio" @if($portada) style="background-image: url('{{ asset($portada) }}');" @endif>
    <div class="container">
        <span class="site-eyebrow" style="color: #ffd166;">Bienvenido a {{ $hotel['nombre'] }}</span>
        <h1 class="site-hero-title">{{ $hotel['eslogan'] }}</h1>
        <p class="site-hero-lead">{{ $hotel['descripcion'] }}</p>

        <div class="site-hero-actions">
            <a href="#habitaciones" class="btn btn-primary btn-lg">
                <i class="bi bi-door-open"></i>&nbsp;Ver habitaciones
            </a>
            <a href="#contacto" class="btn btn-outline-light btn-lg">
                <i class="bi bi-telephone"></i>&nbsp;Contáctanos
            </a>
        </div>

        <div class="site-hero-facts">
            <span><i class="bi bi-geo-alt-fill"></i>{{ $hotel['direccion'] }}</span>
            <span><i class="bi bi-clock-fill"></i>Check-in {{ $hotel['checkin'] }} · Check-out {{ $hotel['checkout'] }}</span>
            <span><i class="bi bi-house-heart-fill"></i>{{ $tipos_habitacion->count() }} tipos de habitación</span>
        </div>
    </div>
</section>

{{-- ======= Habitaciones ======= --}}
<section class="site-section" id="habitaciones">
    <div class="container">
        <span class="site-eyebrow">Habitaciones</span>
        <h2 class="site-section-title">Un espacio para cada viaje</h2>
        <p class="site-section-lead">Precios por noche en dólares, impuestos incluidos. Todas las habitaciones cuentan con baño privado, aire acondicionado y wifi.</p>

        @if($tipos_habitacion->isEmpty())
            <div class="alert alert-info">Muy pronto publicaremos nuestras habitaciones.</div>
        @else
            <div class="row g-4">
                @foreach($tipos_habitacion as $tipo)
                    <div class="col-12 col-md-6 col-lg-4">
                        <article class="room-card">
                            <div class="room-card-media" @if($tipo->imagenes->isNotEmpty()) data-bs-toggle="modal" data-bs-target="#fotosTipo{{ $tipo->id }}" role="button" title="Ver fotos" @endif>
                                @if($tipo->imagenPrincipal)
                                    <img src="{{ $tipo->imagenPrincipal->url }}" alt="Habitación {{ $tipo->nombre }}" loading="lazy">
                                @else
                                    <i class="bi bi-image" aria-hidden="true"></i>
                                @endif
                                @if($tipo->imagenes->count() > 1)
                                    <span class="room-card-count"><i class="bi bi-images"></i> {{ $tipo->imagenes->count() }} fotos</span>
                                @endif
                            </div>

                            <div class="room-card-body">
                                <div class="room-card-head">
                                    <h3 class="room-card-title">{{ $tipo->nombre }}</h3>
                                    <div class="room-card-price">${{ number_format($tipo->precio_base, 2) }} <small>/ noche</small></div>
                                </div>

                                <div class="room-card-meta">
                                    <span><i class="bi bi-people-fill"></i> Hasta {{ $tipo->capacidad }} {{ $tipo->capacidad == 1 ? 'huésped' : 'huéspedes' }}</span>
                                    <span><i class="bi bi-wifi"></i> Wifi</span>
                                    <span><i class="bi bi-snow"></i> A/C</span>
                                </div>

                                <p class="room-card-text">{{ $tipo->descripcion ?: 'Consulta con nosotros los detalles de esta habitación.' }}</p>

                                <div class="room-card-actions">
                                    @if($tipo->imagenes->isNotEmpty())
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#fotosTipo{{ $tipo->id }}">
                                            <i class="bi bi-images"></i>&nbsp;Ver fotos
                                        </button>
                                    @endif
                                    {{-- PENDIENTE: enlazar al motor de reservas cuando exista --}}
                                    <a href="{{ auth()->check() ? '#contacto' : route('login') }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-calendar-check"></i>&nbsp;Reservar
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ======= Servicios ======= --}}
<section class="site-section" id="servicios">
    <div class="container">
        <span class="site-eyebrow">Servicios</span>
        <h2 class="site-section-title">Todo lo que necesitas durante tu estadía</h2>
        <p class="site-section-lead">Servicios complementarios que puedes solicitar al momento de reservar o directamente en recepción.</p>

        <div class="row g-3">
            @foreach($hotel['servicios'] as $servicio)
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="service-item">
                        <div class="service-icon"><i class="bi {{ $servicio['icono'] }}" aria-hidden="true"></i></div>
                        <div>
                            <h3>{{ $servicio['nombre'] }}</h3>
                            <p>{{ $servicio['descripcion'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ======= Galería ======= --}}
@if(!empty($hotel['galeria']))
<section class="site-section" id="galeria">
    <div class="container">
        <span class="site-eyebrow">Galería</span>
        <h2 class="site-section-title">Conoce el hotel</h2>
        <p class="site-section-lead">Nuestras instalaciones frente al mar.</p>

        <div class="gallery-grid">
            @foreach($hotel['galeria'] as $foto)
                <a class="gallery-item" href="{{ asset($foto['archivo']) }}" target="_blank" rel="noopener">
                    <img src="{{ asset($foto['archivo']) }}" alt="{{ $foto['titulo'] }}" loading="lazy">
                    <span>{{ $foto['titulo'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ======= Contacto ======= --}}
<section class="site-section" id="contacto">
    <div class="container">
        <span class="site-eyebrow">Contacto</span>
        <h2 class="site-section-title">Estamos para ayudarte</h2>
        <p class="site-section-lead">Escríbenos o llámanos para consultar disponibilidad, tarifas de grupo o cualquier duda sobre tu visita.</p>

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="contact-card">
                    <ul class="contact-list">
                        <li>
                            <i class="bi bi-geo-alt-fill"></i>
                            <div>
                                {{ $hotel['direccion'] }}
                                <small><a href="{{ $hotel['mapa_url'] }}" target="_blank" rel="noopener">Ver en el mapa <i class="bi bi-box-arrow-up-right"></i></a></small>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-telephone-fill"></i>
                            <div>{{ $hotel['telefono'] }}<small>Recepción, 24 horas</small></div>
                        </li>
                        <li>
                            <i class="bi bi-whatsapp"></i>
                            <div>{{ $hotel['whatsapp'] }}<small>WhatsApp para reservaciones</small></div>
                        </li>
                        <li>
                            <i class="bi bi-envelope-fill"></i>
                            <div>{{ $hotel['email'] }}<small>Respondemos en menos de 24 horas</small></div>
                        </li>
                        <li>
                            <i class="bi bi-clock-fill"></i>
                            <div>Check-in desde las {{ $hotel['checkin'] }} · Check-out hasta las {{ $hotel['checkout'] }}</div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="contact-card">
                    <h3 class="h5 fw-bold mb-2">Reserva en línea</h3>
                    <p class="mb-3" style="color: var(--color-text-muted);">Crea tu cuenta de huésped para consultar disponibilidad, reservar y llevar el historial de tus estadías.</p>
                    @auth
                        <p class="mb-3">Hola, <strong>{{ auth()->user()->name }}</strong>. Muy pronto podrás reservar desde aquí.</p>
                        @can('perfil_show')
                            <a href="{{ url('perfil') }}" class="btn btn-outline-primary"><i class="bi bi-person-circle"></i>&nbsp;Mi cuenta</a>
                        @endcan
                    @else
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('register') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i>&nbsp;Crear cuenta</a>
                            <a href="{{ route('login') }}" class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-right"></i>&nbsp;Iniciar sesión</a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ======= Modales con las fotos de cada tipo ======= --}}
@foreach($tipos_habitacion as $tipo)
    @if($tipo->imagenes->isNotEmpty())
        <div class="modal fade room-modal" id="fotosTipo{{ $tipo->id }}" tabindex="-1" aria-labelledby="fotosTipoLabel{{ $tipo->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="fotosTipoLabel{{ $tipo->id }}">Habitación {{ $tipo->nombre }} · ${{ number_format($tipo->precio_base, 2) }} / noche</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="carruselTipo{{ $tipo->id }}" class="carousel slide" data-bs-ride="false">
                            <div class="carousel-inner">
                                @foreach($tipo->imagenes as $imagen)
                                    <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                                        <img src="{{ $imagen->url }}" alt="Habitación {{ $tipo->nombre }} foto {{ $loop->iteration }}" loading="lazy">
                                    </div>
                                @endforeach
                            </div>
                            @if($tipo->imagenes->count() > 1)
                                <button class="carousel-control-prev" type="button" data-bs-target="#carruselTipo{{ $tipo->id }}" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Anterior</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#carruselTipo{{ $tipo->id }}" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Siguiente</span>
                                </button>
                            @endif
                        </div>
                        @if($tipo->descripcion)
                            <p class="mt-3 mb-0" style="color: var(--color-text-muted);">{{ $tipo->descripcion }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

@endsection
