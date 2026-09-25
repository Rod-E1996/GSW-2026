<!-- ======= Footer ======= -->
@php $hotelPie = config('hotel'); @endphp
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-md-5">
                <h5><i class="bi bi-building" aria-hidden="true"></i>&nbsp;{{ $hotelPie['nombre'] }}</h5>
                <p class="mb-0">{{ $hotelPie['eslogan'] }}</p>
            </div>
            <div class="col-6 col-md-3">
                <h5>Explora</h5>
                <ul class="list-unstyled mb-0 d-grid gap-1">
                    <li><a href="{{ route('home') }}#habitaciones">Habitaciones</a></li>
                    <li><a href="{{ route('home') }}#servicios">Servicios</a></li>
                    <li><a href="{{ route('home') }}#galeria">Galería</a></li>
                    <li><a href="{{ route('home') }}#contacto">Contacto</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <h5>Contacto</h5>
                <ul class="list-unstyled mb-0 d-grid gap-1">
                    <li><i class="bi bi-geo-alt"></i> {{ $hotelPie['direccion'] }}</li>
                    <li><i class="bi bi-telephone"></i> {{ $hotelPie['telefono'] }}</li>
                    <li><i class="bi bi-envelope"></i> {{ $hotelPie['email'] }}</li>
                </ul>
            </div>
        </div>
        <div class="site-footer-bottom">
            &copy; {{ date('Y') }} <strong>{{ $hotelPie['nombre'] }}</strong>. Todos los derechos reservados.
            &middot; Portal de reservaciones desarrollado por el Equipo #6, ITCA-FEPADE.
        </div>
    </div>
</footer>
<!-- End Footer -->
