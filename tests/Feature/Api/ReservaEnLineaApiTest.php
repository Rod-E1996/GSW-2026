<?php

namespace Tests\Feature\Api;

use App\Models\CategoriaServicio;
use App\Models\Configuracion;
use App\Models\Habitacion;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Temporada;
use App\Models\TipoHabitacion;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Pantalla de reserva: resumen con servicios, datos del titular y correo verificado.
 */
class ReservaEnLineaApiTest extends ApiTestCase
{
    private Habitacion $habitacion;
    private array $servicio = [];

    protected function setUp(): void
    {
        parent::setUp();

        Temporada::query()->update(['estado' => 0]);
        Configuracion::where('clave', 'impuesto_porcentaje')->update(['valor' => '10']);
        Configuracion::where('clave', 'anticipo_porcentaje')->update(['valor' => '50']);

        $tipo = TipoHabitacion::create(['nombre' => 'En linea ' . uniqid(), 'capacidad' => 3, 'precio_base' => 100]);
        $this->habitacion = Habitacion::create(['numero' => 'L' . rand(100, 999), 'piso' => 1, 'tipo_habitacion_id' => $tipo->id]);

        $categoria = CategoriaServicio::create(['nombre' => 'Pruebas ' . uniqid()]);
        foreach ([
            'persona_noche' => [Servicio::POR_PERSONA_NOCHE, 10, false],
            'persona' => [Servicio::POR_PERSONA, 7, false],
            'noche' => [Servicio::POR_NOCHE, 5, false],
            'fijo' => [Servicio::FIJO, 30, false],
            'con_fecha' => [Servicio::FIJO, 40, true],
        ] as $clave => [$forma, $precio, $fecha]) {
            $this->servicio[$clave] = Servicio::create([
                'categoria_servicio_id' => $categoria->id,
                'nombre' => "Servicio $clave " . uniqid(),
                'precio' => $precio,
                'forma_cobro' => $forma,
                'requiere_fecha' => $fecha,
            ]);
        }
    }

    private function estadia(array $extra = []): array
    {
        return array_merge([
            'habitacion_id' => $this->habitacion->id,
            'fecha_llegada' => '2041-02-10',
            'fecha_salida' => '2041-02-13',
            'adultos' => 2,
        ], $extra);
    }

    private function titular(array $extra = []): array
    {
        return array_merge([
            'nombres' => 'Marta',
            'apellidos' => 'Quintanilla',
            'tipo_documento' => 'Pasaporte',
            'documento' => 'P1234567',
            'telefono' => '78889999',
        ], $extra);
    }

    public function test_el_catalogo_publico_solo_muestra_servicios_activos()
    {
        $this->servicio['noche']->update(['estado' => 0]);

        $ids = array_column($this->getJson('/api/servicios')->assertOk()->json('data'), 'id');

        $this->assertContains($this->servicio['persona']->id, $ids);
        $this->assertNotContains($this->servicio['noche']->id, $ids);
    }

    public function test_cotizar_calcula_cada_forma_de_cobro_impuesto_y_anticipo()
    {
        //2 personas, 3 noches
        $res = $this->postJson('/api/reservas/cotizar', $this->estadia(['servicios' => [
            ['servicio_id' => $this->servicio['persona_noche']->id],
            ['servicio_id' => $this->servicio['persona']->id],
            ['servicio_id' => $this->servicio['noche']->id],
            ['servicio_id' => $this->servicio['fijo']->id, 'cantidad' => 2],
        ]]))->assertOk();

        $this->assertSame([6, 2, 3, 2], array_column($res->json('data.servicios'), 'cantidad'));
        $this->assertSame([60, 14, 15, 60], array_column($res->json('data.servicios'), 'subtotal'));
        $res->assertJsonPath('data.subtotal_alojamiento', 300)
            ->assertJsonPath('data.subtotal_servicios', 149)
            ->assertJsonPath('data.impuesto', 44.9)
            ->assertJsonPath('data.total', 493.9)
            ->assertJsonPath('data.anticipo_requerido', 246.95)
            ->assertJsonPath('data.disponible', true);
    }

    public function test_un_servicio_con_fecha_la_exige_dentro_de_la_estadia()
    {
        $id = $this->servicio['con_fecha']->id;

        $this->postJson('/api/reservas/cotizar', $this->estadia(['servicios' => [['servicio_id' => $id]]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['servicios.0.fecha_servicio']);

        $this->postJson('/api/reservas/cotizar', $this->estadia(['servicios' => [['servicio_id' => $id, 'fecha_servicio' => '2041-02-20']]]))
            ->assertStatus(422);

        $this->postJson('/api/reservas/cotizar', $this->estadia(['servicios' => [['servicio_id' => $id, 'fecha_servicio' => '2041-02-13', 'hora_servicio' => '09:30']]]))
            ->assertOk()
            ->assertJsonPath('data.servicios.0.hora_servicio', '09:30');
    }

    public function test_no_acepta_servicios_inactivos_ni_repetidos()
    {
        $this->servicio['fijo']->update(['estado' => 0]);

        $this->postJson('/api/reservas/cotizar', $this->estadia(['servicios' => [['servicio_id' => $this->servicio['fijo']->id]]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['servicios.0.servicio_id']);

        $id = $this->servicio['noche']->id;
        $this->postJson('/api/reservas/cotizar', $this->estadia(['servicios' => [['servicio_id' => $id], ['servicio_id' => $id]]]))
            ->assertStatus(422);
    }

    public function test_cotizar_avisa_si_la_habitacion_ya_no_esta_libre()
    {
        $this->como($this->huesped())->postJson('/api/reservas', $this->estadia() + $this->titular())->assertCreated();

        $this->postJson('/api/reservas/cotizar', $this->estadia())->assertOk()->assertJsonPath('data.disponible', false);
    }

    public function test_reservar_guarda_servicios_y_actualiza_la_ficha_del_titular()
    {
        $huesped = $this->huesped();

        $codigo = $this->como($huesped)->postJson('/api/reservas', $this->estadia(['ninos' => 1, 'servicios' => [
            ['servicio_id' => $this->servicio['persona']->id],
            ['servicio_id' => $this->servicio['con_fecha']->id, 'fecha_servicio' => '2041-02-11', 'hora_servicio' => '18:00'],
        ]]) + $this->titular())->assertCreated()->json('data.codigo');

        $reserva = Reserva::with('servicios', 'huesped')->where('codigo', $codigo)->firstOrFail();

        //3 personas: el servicio por persona cuenta tambien al nino
        $this->assertEquals(21, $reserva->servicios->firstWhere('id', $this->servicio['persona']->id)->pivot->subtotal);
        $conFecha = $reserva->servicios->firstWhere('id', $this->servicio['con_fecha']->id)->pivot;
        $this->assertSame('2041-02-11', substr($conFecha->fecha_servicio, 0, 10));
        $this->assertSame('61.00', $reserva->subtotal_servicios);
        $this->assertSame('36.10', $reserva->impuesto);
        $this->assertSame('397.10', $reserva->total);
        $this->assertSame('198.55', $reserva->anticipo_requerido);

        $this->assertSame('Marta', $reserva->huesped->nombres);
        $this->assertSame('P1234567', $reserva->huesped->documento);
        $this->assertSame($huesped->huesped->id, $reserva->huesped_id);
    }

    public function test_reservar_exige_los_datos_del_titular()
    {
        $this->como($this->huesped())
            ->postJson('/api/reservas', $this->estadia())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombres', 'apellidos', 'tipo_documento', 'documento', 'telefono']);
    }

    public function test_sin_correo_verificado_no_puede_reservar()
    {
        $user = User::create(['name' => 'Sin Verificar', 'email' => 'sinverificar@test.com', 'password' => Hash::make('x')]);
        $user->assignRole('Huésped');

        $this->como($user)
            ->postJson('/api/reservas', $this->estadia() + $this->titular())
            ->assertForbidden()
            ->assertJsonPath('codigo', 'correo_sin_verificar');

        $this->assertSame(0, Reserva::where('habitacion_id', $this->habitacion->id)->count());

        //Cotizar si puede: solo muestra precios
        $this->como($user)->postJson('/api/reservas/cotizar', $this->estadia())->assertOk();
    }
}
