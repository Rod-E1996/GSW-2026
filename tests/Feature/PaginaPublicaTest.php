<?php

namespace Tests\Feature;

use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Verifica que la página pública muestre la información del hotel
 * y los tipos de habitación activos con sus fotos.
 */
class PaginaPublicaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_muestra_datos_del_hotel_y_secciones()
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('hotel.nombre'))
            ->assertSee(config('hotel.eslogan'))
            ->assertSee(config('hotel.telefono'))
            ->assertSee('id="habitaciones"', false)
            ->assertSee('id="servicios"', false)
            ->assertSee('id="galeria"', false)
            ->assertSee('id="contacto"', false)
            ->assertSee('Iniciar sesi');
    }

    public function test_lista_solo_tipos_activos_con_precio_capacidad_y_foto()
    {
        $activo = TipoHabitacion::create(['nombre' => 'Publico Activo', 'capacidad' => 3, 'precio_base' => 77.5, 'descripcion' => 'Con vista al mar de prueba']);
        TipoHabitacion::create(['nombre' => 'Publico Inactivo', 'capacidad' => 1, 'precio_base' => 10, 'estado' => 0]);
        TipoHabitacionImagen::create(['tipo_habitacion_id' => $activo->id, 'ruta' => 'x/publica.jpg', 'orden' => 1, 'principal' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Publico Activo')
            ->assertSee('$77.50')
            ->assertSee('Hasta 3 hu')
            ->assertSee('Con vista al mar de prueba')
            ->assertSee('storage/x/publica.jpg')
            ->assertSee('fotosTipo' . $activo->id)
            ->assertDontSee('Publico Inactivo');
    }

    public function test_usuario_logueado_ve_su_nombre_y_no_el_boton_de_registro()
    {
        $huesped = User::where('email', 'huesped@hotellink.com')->firstOrFail();

        $this->actingAs($huesped)
            ->get('/')
            ->assertOk()
            ->assertSee($huesped->name)
            ->assertSee('Mi cuenta')
            ->assertDontSee('Crear cuenta')
            ->assertDontSee('Administraci');
    }

    public function test_super_administrador_ve_el_enlace_a_administracion()
    {
        $this->actingAs(User::findOrFail(1))
            ->get('/')
            ->assertOk()
            ->assertSee('Administraci');
    }
}
