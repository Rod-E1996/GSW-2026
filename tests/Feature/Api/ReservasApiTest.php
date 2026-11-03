<?php

namespace Tests\Feature\Api;

use App\Exceptions\HabitacionNoDisponible;
use App\Models\BloqueoHabitacion;
use App\Models\Configuracion;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Models\Temporada;
use App\Models\TipoHabitacion;
use App\Services\ReservaService;

/**
 * Creacion de reservas. La concurrencia real se prueba sobre MariaDB en tests/Concurrencia.
 */
class ReservasApiTest extends ApiTestCase
{
    private TipoHabitacion $tipo;
    private Habitacion $habitacion;

    protected function setUp(): void
    {
        parent::setUp();

        Temporada::query()->update(['estado' => 0]);
        Configuracion::where('clave', 'impuesto_porcentaje')->update(['valor' => '13']);
        Configuracion::where('clave', 'anticipo_porcentaje')->update(['valor' => '30']);

        $this->tipo = TipoHabitacion::create(['nombre' => 'Reservable ' . uniqid(), 'capacidad' => 2, 'precio_base' => 100]);
        $this->habitacion = Habitacion::create([
            'numero' => 'R' . rand(100, 999),
            'piso' => 1,
            'tipo_habitacion_id' => $this->tipo->id,
        ]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'habitacion_id' => $this->habitacion->id,
            'fecha_llegada' => '2040-05-10',
            'fecha_salida' => '2040-05-12',
            'adultos' => 2,
        ], $extra);
    }

    public function test_exige_sesion_y_permiso()
    {
        $this->postJson('/api/reservas', $this->datos())->assertUnauthorized();
        $this->como($this->recepcion())->postJson('/api/reservas', $this->datos())->assertForbidden();
        $this->assertSame(0, Reserva::where('habitacion_id', $this->habitacion->id)->count());
    }

    public function test_crea_la_reserva_con_codigo_importes_noches_e_historial()
    {
        $huesped = $this->huesped();

        $res = $this->como($huesped)->postJson('/api/reservas', $this->datos(['ninos' => 0, 'notas' => 'Cuna']))->assertCreated();

        $res->assertJsonPath('data.subtotal_alojamiento', 200)
            ->assertJsonPath('data.impuesto', 26)
            ->assertJsonPath('data.total', 226)
            ->assertJsonPath('data.anticipo_requerido', 67.8)
            ->assertJsonPath('data.estado', 'Pendiente');

        $reserva = Reserva::where('codigo', $res->json('data.codigo'))->firstOrFail();

        $this->assertMatchesRegularExpression('/^HL-[A-HJ-NP-Z2-9]{8}$/', $reserva->codigo);
        $this->assertSame($huesped->huesped->id, $reserva->huesped_id);
        $this->assertSame($huesped->id, $reserva->user_id);
        $this->assertSame(Reserva::CANAL_WEB, $reserva->canal);
        $this->assertSame($this->tipo->id, $reserva->tipo_habitacion_id);
        $this->assertSame(['2040-05-10', '2040-05-11'], $reserva->noches->map(fn ($n) => $n->fecha->toDateString())->all());
        $this->assertSame($this->habitacion->id, $reserva->noches->first()->habitacion_id);
        $this->assertCount(1, $reserva->historial);
    }

    public function test_guarda_la_temporada_y_el_precio_de_cada_noche()
    {
        $temporada = Temporada::create(['nombre' => 'Alta', 'fecha_inicio' => '2040-05-11', 'fecha_fin' => '2040-05-20', 'multiplicador' => 1.5]);

        $codigo = $this->como($this->huesped())->postJson('/api/reservas', $this->datos())->assertCreated()->json('data.codigo');

        $noches = Reserva::where('codigo', $codigo)->firstOrFail()->noches;
        $this->assertSame(['100.00', '150.00'], $noches->pluck('precio_noche')->all());
        $this->assertSame([null, $temporada->id], $noches->pluck('temporada_id')->all());
        $this->assertSame('250.00', Reserva::where('codigo', $codigo)->first()->subtotal_alojamiento);
    }

    public function test_el_cliente_no_puede_imponer_precio_estado_ni_titular()
    {
        $otro = Huesped::create(['nombres' => 'Otra', 'apellidos' => 'Persona']);

        $codigo = $this->como($this->huesped())
            ->postJson('/api/reservas', $this->datos([
                'total' => 1,
                'subtotal_alojamiento' => 1,
                'estado_reserva' => Reserva::CONFIRMADA,
                'huesped_id' => $otro->id,
                'codigo' => 'HL-ELEGIDO',
                'tipo_habitacion_id' => TipoHabitacion::where('id', '!=', $this->tipo->id)->first()->id,
            ]))
            ->assertCreated()
            ->json('data.codigo');

        $reserva = Reserva::where('codigo', $codigo)->firstOrFail();
        $this->assertNotSame('HL-ELEGIDO', $codigo);
        $this->assertSame('226.00', $reserva->total);
        $this->assertSame(Reserva::PENDIENTE, $reserva->estado_reserva);
        $this->assertNotSame($otro->id, $reserva->huesped_id);
        $this->assertSame($this->tipo->id, $reserva->tipo_habitacion_id);
    }

    public function test_si_ya_esta_ocupada_informa_y_ofrece_alternativas()
    {
        $this->como($this->huesped())->postJson('/api/reservas', $this->datos())->assertCreated();

        $res = $this->como($this->huesped())
            ->postJson('/api/reservas', $this->datos(['fecha_llegada' => '2040-05-11', 'fecha_salida' => '2040-05-13']))
            ->assertStatus(409);

        $res->assertJsonStructure(['message', 'alternativas' => ['tipos', 'fechas']]);
        $this->assertNotEmpty($res->json('alternativas.fechas'));
        $this->assertSame(1, Reserva::where('habitacion_id', $this->habitacion->id)->count());
    }

    public function test_si_le_ganan_la_habitacion_ofrece_otra_del_mismo_tipo()
    {
        Habitacion::create(['numero' => 'RG' . rand(100, 999), 'piso' => 1, 'tipo_habitacion_id' => $this->tipo->id]);
        $this->como($this->huesped())->postJson('/api/reservas', $this->datos())->assertCreated();

        $res = $this->como($this->huesped())->postJson('/api/reservas', $this->datos())->assertStatus(409);

        $delTipo = collect($res->json('alternativas.tipos'))->firstWhere('id', $this->tipo->id);
        $this->assertSame(1, $delTipo['disponibles']);
    }

    public function test_salida_y_entrada_el_mismo_dia_se_permiten()
    {
        $this->como($this->huesped())->postJson('/api/reservas', $this->datos())->assertCreated();

        $this->como($this->huesped())
            ->postJson('/api/reservas', $this->datos(['fecha_llegada' => '2040-05-12', 'fecha_salida' => '2040-05-14']))
            ->assertCreated();

        $this->assertSame(2, Reserva::where('habitacion_id', $this->habitacion->id)->count());
    }

    public function test_no_reserva_sobre_un_bloqueo_ni_en_mantenimiento()
    {
        BloqueoHabitacion::create([
            'habitacion_id' => $this->habitacion->id,
            'fecha_inicio' => '2040-05-11',
            'fecha_fin' => '2040-05-11',
            'motivo' => BloqueoHabitacion::MANTENIMIENTO,
        ]);
        $this->como($this->huesped())->postJson('/api/reservas', $this->datos())->assertStatus(409);

        $this->habitacion->update(['estado_habitacion' => Habitacion::MANTENIMIENTO]);
        $this->como($this->huesped())
            ->postJson('/api/reservas', $this->datos(['fecha_llegada' => '2040-06-01', 'fecha_salida' => '2040-06-02']))
            ->assertStatus(409);
    }

    public function test_respeta_las_reservas_sin_habitacion_asignada_del_tipo()
    {
        Reserva::create([
            'codigo' => Reserva::generarCodigo(),
            'huesped_id' => Reserva::firstOrFail()->huesped_id,
            'tipo_habitacion_id' => $this->tipo->id,
            'fecha_llegada' => '2040-05-10',
            'fecha_salida' => '2040-05-12',
            'adultos' => 1,
            'estado_reserva' => Reserva::CONFIRMADA,
        ]);

        $this->como($this->huesped())->postJson('/api/reservas', $this->datos())->assertStatus(409);
    }

    public function test_valida_fechas_capacidad_y_habitacion()
    {
        $yo = $this->huesped();

        $this->como($yo)
            ->postJson('/api/reservas', $this->datos(['fecha_llegada' => '2020-01-01', 'fecha_salida' => '2020-01-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_llegada', 'fecha_salida']);

        $this->como($yo)
            ->postJson('/api/reservas', $this->datos(['adultos' => 2, 'ninos' => 1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['adultos']);

        $this->como($yo)
            ->postJson('/api/reservas', $this->datos(['fecha_salida' => '2040-08-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_salida']);

        $eliminada = Habitacion::create(['numero' => 'RX' . rand(10, 99), 'piso' => 1, 'tipo_habitacion_id' => $this->tipo->id, 'estado' => 0]);
        $this->como($yo)
            ->postJson('/api/reservas', $this->datos(['habitacion_id' => $eliminada->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['habitacion_id']);
    }

    public function test_si_falla_algo_no_queda_una_reserva_a_medias()
    {
        Reserva::created(function () {
            throw new \RuntimeException('Fallo simulado');
        });

        try {
            app(ReservaService::class)->crear([
                'habitacion_id' => $this->habitacion->id,
                'huesped_id' => Reserva::firstOrFail()->huesped_id,
                'fecha_llegada' => '2040-05-10',
                'fecha_salida' => '2040-05-12',
                'adultos' => 1,
            ]);
            $this->fail('Debio propagar el error');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado', $e->getMessage());
        }

        $this->assertSame(0, Reserva::where('habitacion_id', $this->habitacion->id)->count());
    }

    public function test_el_servicio_avisa_con_una_excepcion_propia()
    {
        $datos = [
            'habitacion_id' => $this->habitacion->id,
            'huesped_id' => Reserva::firstOrFail()->huesped_id,
            'fecha_llegada' => '2040-05-10',
            'fecha_salida' => '2040-05-12',
            'adultos' => 1,
        ];

        app(ReservaService::class)->crear($datos);

        $this->expectException(HabitacionNoDisponible::class);
        app(ReservaService::class)->crear($datos);
    }

    public function test_los_codigos_no_se_repiten_ni_son_consecutivos()
    {
        $codigos = collect(range(1, 200))->map(fn () => Reserva::generarCodigo());

        $this->assertSame(200, $codigos->unique()->count());
    }

    public function test_un_bloqueo_no_se_puede_crear_sobre_una_reserva_nueva()
    {
        $this->como($this->huesped())->postJson('/api/reservas', $this->datos())->assertCreated();

        $this->como($this->admin())
            ->postJson('/api/admin/bloqueos', [
                'habitacion_id' => $this->habitacion->id,
                'fecha_inicio' => '2040-05-11',
                'fecha_fin' => '2040-05-11',
                'motivo' => BloqueoHabitacion::MANTENIMIENTO,
            ])
            ->assertStatus(422);
    }

    public function test_el_huesped_de_las_seeds_tiene_el_permiso_y_recepcion_no()
    {
        $this->assertTrue($this->huesped()->can('reserva_solicitar'));
        $this->assertFalse($this->recepcion()->can('reserva_solicitar'));
    }
}
