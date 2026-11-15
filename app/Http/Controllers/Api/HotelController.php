<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * Datos del hotel para el portal: contacto y galeria general (config/hotel.php).
 */
class HotelController extends Controller
{
    public function show()
    {
        $hotel = config('hotel');

        return response()->json([
            'data' => [
                'nombre' => $hotel['nombre'],
                'direccion' => $hotel['direccion'],
                'telefono' => $hotel['telefono'],
                'whatsapp' => $hotel['whatsapp'],
                'email' => $hotel['email'],
                'checkin' => $hotel['checkin'],
                'checkout' => $hotel['checkout'],
                'mapa_url' => $hotel['mapa_url'],
                'galeria' => collect($hotel['galeria'])->map(fn ($foto) => [
                    'url' => asset($foto['archivo']),
                    'titulo' => $foto['titulo'],
                ])->values(),
            ],
        ]);
    }
}
