<?php

/*
|--------------------------------------------------------------------------
| Datos del hotel
|--------------------------------------------------------------------------
|
| Información institucional que se muestra en el sitio público, correos y
| comprobantes. El sistema administra un único establecimiento (limitación
| del documento), así que vive en configuración y no en base de datos.
| Se puede sobreescribir desde el .env con las variables HOTEL_*.
|
*/

return [

    'nombre' => env('HOTEL_NOMBRE', 'HotelLink'),

    'eslogan' => env('HOTEL_ESLOGAN', 'Tu descanso frente al mar, a un clic de distancia'),

    'descripcion' => env('HOTEL_DESCRIPCION',
        'Somos un hotel familiar en la costa de La Libertad, El Salvador. Ofrecemos habitaciones cómodas, ' .
        'piscina, restaurante y atención personalizada para que disfrutes del sol, el surf y la gastronomía local.'
    ),

    'direccion' => env('HOTEL_DIRECCION', 'Km 42 Carretera del Litoral, Playa El Tunco, La Libertad, El Salvador'),

    'telefono' => env('HOTEL_TELEFONO', '+503 2389-6000'),

    'whatsapp' => env('HOTEL_WHATSAPP', '+503 7000-0000'),

    'email' => env('HOTEL_EMAIL', 'reservas@hotellink.com'),

    'checkin' => env('HOTEL_CHECKIN', '14:00'),

    'checkout' => env('HOTEL_CHECKOUT', '12:00'),

    //Enlace a Google Maps (se abre en una pestaña nueva)
    'mapa_url' => env('HOTEL_MAPA_URL', 'https://www.google.com/maps/search/?api=1&query=Playa+El+Tunco+La+Libertad+El+Salvador'),

    //Datos rapidos que se muestran en el hero
    'fundacion' => env('HOTEL_FUNDACION', 2015),

    //Fotos de la galeria general (fachada, piscina, etc.) en public/img/hotel.
    //La primera se usa como fondo del hero.
    'galeria' => [
        ['archivo' => 'img/hotel/01-piscina-tropical.jpg', 'titulo' => 'Piscina y jardines'],
        ['archivo' => 'img/hotel/02-fachada.jpg',          'titulo' => 'Fachada principal'],
        ['archivo' => 'img/hotel/03-piscina-noche.jpg',    'titulo' => 'Piscina de noche'],
        ['archivo' => 'img/hotel/04-terraza-mar.jpg',      'titulo' => 'Terraza frente al mar'],
    ],

    //Servicios complementarios que se muestran en el sitio publico.
    //PENDIENTE: reemplazar por la tabla "servicios" cuando exista ese modulo.
    'servicios' => [
        ['icono' => 'bi-cup-hot',        'nombre' => 'Alimentación',          'descripcion' => 'Desayuno, almuerzo y cena en el restaurante o en tu habitación.'],
        ['icono' => 'bi-basket',         'nombre' => 'Lavandería',            'descripcion' => 'Servicio de lavado y planchado con entrega el mismo día.'],
        ['icono' => 'bi-car-front',      'nombre' => 'Transporte',            'descripcion' => 'Traslados desde y hacia el aeropuerto y tours por la zona.'],
        ['icono' => 'bi-people',         'nombre' => 'Salón de eventos',      'descripcion' => 'Espacio para reuniones, celebraciones y bodas frente al mar.'],
        ['icono' => 'bi-water',          'nombre' => 'Actividades recreativas','descripcion' => 'Clases de surf, kayak y caminatas guiadas.'],
    ],

];
