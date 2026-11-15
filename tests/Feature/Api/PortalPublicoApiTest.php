<?php

namespace Tests\Feature\Api;

/**
 * Datos del hotel que muestra el portal React: contacto y galeria.
 */
class PortalPublicoApiTest extends ApiTestCase
{
    public function test_datos_de_contacto_y_galeria_sin_iniciar_sesion()
    {
        config(['hotel.telefono' => '+503 2222-0000']);

        $res = $this->getJson('/api/hotel')
            ->assertOk()
            ->assertJsonPath('data.telefono', '+503 2222-0000')
            ->assertJsonStructure(['data' => ['direccion', 'email', 'checkin', 'checkout', 'mapa_url', 'galeria' => [['url', 'titulo']]]]);

        $this->assertCount(count(config('hotel.galeria')), $res->json('data.galeria'));
        $this->assertStringEndsWith(config('hotel.galeria.0.archivo'), $res->json('data.galeria.0.url'));
    }
}
