<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CategoriaServicio;
use App\Models\Servicio;

/**
 * Seeder del catalogo de servicios complementarios.
 * Saca a base de datos los 5 servicios que antes vivian en config/hotel.php.
 * Es seguro re-ejecutarlo: solo inserta si la tabla esta vacia.
 */
class ServiciosSeeder extends Seeder
{
    public function run()
    {
        if (Servicio::count() > 0) {
            return;
        }

        $categorias = [];
        foreach ([
            ['nombre' => 'Alimentación y bebidas', 'nombre_en' => 'Food and drinks', 'icono' => 'bi-cup-hot', 'orden' => 1],
            ['nombre' => 'Servicios al huésped', 'nombre_en' => 'Guest services', 'icono' => 'bi-bell', 'orden' => 2],
            ['nombre' => 'Eventos', 'nombre_en' => 'Events', 'icono' => 'bi-people', 'orden' => 3],
            ['nombre' => 'Recreación', 'nombre_en' => 'Recreation', 'icono' => 'bi-water', 'orden' => 4],
            ['nombre' => 'Bienestar', 'nombre_en' => 'Wellness', 'icono' => 'bi-flower1', 'orden' => 5],
        ] as $categoria) {
            $categorias[$categoria['nombre']] = CategoriaServicio::firstOrCreate(['nombre' => $categoria['nombre']], $categoria)->id;
        }

        $servicios = [
            [
                'categoria_servicio_id' => $categorias['Alimentación y bebidas'],
                'nombre' => 'Alimentación',
                'nombre_en' => 'Meals',
                'descripcion' => 'Desayuno, almuerzo y cena en el restaurante o en tu habitación.',
                'icono' => 'bi-cup-hot',
                'precio' => 12.00,
                'forma_cobro' => Servicio::POR_PERSONA_NOCHE,
            ],
            [
                'categoria_servicio_id' => $categorias['Servicios al huésped'],
                'nombre' => 'Lavandería',
                'nombre_en' => 'Laundry',
                'descripcion' => 'Servicio de lavado y planchado con entrega el mismo día.',
                'icono' => 'bi-basket',
                'precio' => 8.00,
                'forma_cobro' => Servicio::FIJO,
            ],
            [
                'categoria_servicio_id' => $categorias['Servicios al huésped'],
                'nombre' => 'Transporte',
                'nombre_en' => 'Transport',
                'descripcion' => 'Traslados desde y hacia el aeropuerto y tours por la zona.',
                'icono' => 'bi-car-front',
                'precio' => 45.00,
                'forma_cobro' => Servicio::FIJO,
            ],
            [
                'categoria_servicio_id' => $categorias['Eventos'],
                'nombre' => 'Salón de eventos',
                'nombre_en' => 'Event hall',
                'descripcion' => 'Espacio para reuniones, celebraciones y bodas frente al mar.',
                'icono' => 'bi-people',
                'precio' => 300.00,
                'forma_cobro' => Servicio::FIJO,
            ],
            [
                'categoria_servicio_id' => $categorias['Recreación'],
                'nombre' => 'Actividades recreativas',
                'nombre_en' => 'Recreational activities',
                'descripcion' => 'Clases de surf, kayak y caminatas guiadas.',
                'icono' => 'bi-water',
                'precio' => 35.00,
                'forma_cobro' => Servicio::POR_PERSONA,
            ],
            [
                'categoria_servicio_id' => $categorias['Recreación'],
                'nombre' => 'Tour Ruta de las Flores',
                'nombre_en' => 'Flower Route tour',
                'descripcion' => 'Día completo por Juayúa, Apaneca y Ataco con almuerzo típico incluido.',
                'descripcion_en' => 'Full-day trip to Juayúa, Apaneca and Ataco with a traditional lunch.',
                'icono' => 'bi-geo-alt',
                'precio' => 55.00,
                'forma_cobro' => Servicio::POR_PERSONA,
                'requiere_fecha' => true,
            ],
            [
                'categoria_servicio_id' => $categorias['Bienestar'],
                'nombre' => 'Masaje relajante',
                'nombre_en' => 'Relaxing massage',
                'descripcion' => 'Sesión de 60 minutos en la cabaña de spa frente al mar.',
                'descripcion_en' => '60-minute session in the beachfront spa cabin.',
                'icono' => 'bi-flower1',
                'precio' => 40.00,
                'forma_cobro' => Servicio::POR_PERSONA,
                'requiere_fecha' => true,
            ],
            [
                'categoria_servicio_id' => $categorias['Alimentación y bebidas'],
                'nombre' => 'Cena romántica en la playa',
                'nombre_en' => 'Romantic beach dinner',
                'descripcion' => 'Cena de tres tiempos para dos personas, a la luz de las antorchas.',
                'descripcion_en' => 'Three-course dinner for two by torchlight.',
                'icono' => 'bi-heart',
                'precio' => 65.00,
                'forma_cobro' => Servicio::FIJO,
                'requiere_fecha' => true,
            ],
            [
                'categoria_servicio_id' => $categorias['Servicios al huésped'],
                'nombre' => 'Estacionamiento vigilado',
                'nombre_en' => 'Guarded parking',
                'descripcion' => 'Parqueo techado con vigilancia las 24 horas.',
                'descripcion_en' => 'Covered parking with 24-hour security.',
                'icono' => 'bi-p-square',
                'precio' => 5.00,
                'forma_cobro' => Servicio::POR_NOCHE,
            ],
            [
                'categoria_servicio_id' => $categorias['Servicios al huésped'],
                'nombre' => 'Salida tardía',
                'nombre_en' => 'Late check-out',
                'descripcion' => 'Conserva la habitación hasta las 4:00 p. m. del día de salida.',
                'descripcion_en' => 'Keep your room until 4:00 p.m. on departure day.',
                'icono' => 'bi-clock-history',
                'precio' => 25.00,
                'forma_cobro' => Servicio::FIJO,
            ],
        ];

        foreach ($servicios as $servicio) {
            Servicio::create($servicio);
        }
    }
}
