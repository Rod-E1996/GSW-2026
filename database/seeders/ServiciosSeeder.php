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
        ];

        foreach ($servicios as $servicio) {
            Servicio::create($servicio);
        }
    }
}
