<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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

        $servicios = [
            [
                'nombre' => 'Alimentación',
                'descripcion' => 'Desayuno, almuerzo y cena en el restaurante o en tu habitación.',
                'icono' => 'bi-cup-hot',
                'precio' => 12.00,
                'forma_cobro' => Servicio::POR_PERSONA_NOCHE,
            ],
            [
                'nombre' => 'Lavandería',
                'descripcion' => 'Servicio de lavado y planchado con entrega el mismo día.',
                'icono' => 'bi-basket',
                'precio' => 8.00,
                'forma_cobro' => Servicio::FIJO,
            ],
            [
                'nombre' => 'Transporte',
                'descripcion' => 'Traslados desde y hacia el aeropuerto y tours por la zona.',
                'icono' => 'bi-car-front',
                'precio' => 45.00,
                'forma_cobro' => Servicio::FIJO,
            ],
            [
                'nombre' => 'Salón de eventos',
                'descripcion' => 'Espacio para reuniones, celebraciones y bodas frente al mar.',
                'icono' => 'bi-people',
                'precio' => 300.00,
                'forma_cobro' => Servicio::FIJO,
            ],
            [
                'nombre' => 'Actividades recreativas',
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
