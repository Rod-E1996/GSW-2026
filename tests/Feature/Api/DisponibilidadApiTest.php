<?php

namespace Tests\Feature\Api;

use App\Models\BloqueoHabitacion;
use App\Models\Configuracion;
use App\Models\Habitacion;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Temporada;
use App\Models\TipoHabitacion;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regla central: que se ofrece y que no. Las fechas son lejanas para no chocar con los datos de ejemplo.
 */
class DisponibilidadApiTest extends ApiTestCase
{
    private TipoHabitacion $tipo;
    private Habitacion $habitacion;

    protected function setUp(): void
    {
        parent::setUp();

        //Solo el inventario de cada prueba cuenta
        Habitacion::query()->update(['estado' => 0]);
        Temporada::query()->update(['estado' => 0]);

        $this->tipo = $this->tipo('Vista mar', 2, 100);
        $this->habitacion = $this->habitacion($this->tipo, 'D1');
    }

    private function tipo(string $nombre, int $capacidad, float $precio): TipoHabitacion
    {
        return TipoHabitacion::create(['nombre' => $nombre, 'capacidad' => $capacidad, 'precio_base' => $precio]);
    }

    private function habitacion(TipoHabitacion $tipo, string $numero, array $extra = []): Habitacion
    {
        return Habitacion::create(array_merge([
            'numero' => $numero,
            'piso' => 1,
            'tipo_habitacion_id' => $tipo->id,
            'estado_habitacion' => Habitacion::DISPONIBLE,
        ], $extra));
    }

    private function reserva(?Habitacion $habitacion, string $llegada, string $salida, int $estado = Reserva::CONFIRMADA, array $extra = []): Reserva
    {
        return Reserva::create(array_merge([
            'codigo' => Reserva::generarCodigo(),
            'huesped_id' => Reserva::firstOrFail()->huesped_id,
            'tipo_habitacion_id' => $habitacion->tipo_habitacion_id ?? $this->tipo->id,
            'habitacion_id' => $habitacion?->id,
            'fecha_llegada' => $llegada,
            'fecha_salida' => $salida,
            'adultos' => 1,
            'estado_reserva' => $estado,
        ], $extra));
    }

    private function buscar(string $llegada = '2040-03-10', string $salida = '2040-03-13', array $extra = [])
    {
        return $this->getJson('/api/disponibilidad?' . http_build_query(array_merge([
            'llegada' => $llegada,
            'salida' => $salida,
            'huespedes' => 1,
        ], $extra)));
    }

    private function numeros($respuesta): array
    {
        return array_column($respuesta->json('data'), 'numero');
    }

    public function test_es_publica_y_valida_los_datos()
    {
        $this->buscar()->assertOk();

        $this->getJson('/api/disponibilidad')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['llegada', 'salida', 'huespedes']);

        $this->buscar('2020-01-01', '2020-01-03')->assertStatus(422)->assertJsonValidationErrors(['llegada']);
        $this->buscar('2040-03-10', '2040-03-10')->assertStatus(422)->assertJsonValidationErrors(['salida']);
        $this->buscar('2040-03-12', '2040-03-10')->assertStatus(422)->assertJsonValidationErrors(['salida']);
        $this->buscar('2040-03-10', '2040-03-12', ['huespedes' => 0])->assertStatus(422)->assertJsonValidationErrors(['huespedes']);
    }

    public function test_los_mensajes_de_fechas_se_entienden()
    {
        $this->buscar('2020-01-01', '2020-01-03')->assertJsonPath('errors.llegada.0', 'La llegada no puede ser una fecha pasada.');
        $this->buscar('2040-03-10', '2040-03-10')->assertJsonPath('errors.salida.0', 'La salida debe ser posterior a la llegada.');
    }

    public function test_no_acepta_una_estadia_demasiado_larga()
    {
        $this->buscar('2040-01-01', '2040-03-01')->assertOk();
        $this->buscar('2040-01-01', '2040-03-02')->assertStatus(422)->assertJsonValidationErrors(['salida']);
    }

    public function test_hoy_se_puede_buscar_pero_ayer_no()
    {
        $hoy = now()->toDateString();

        $this->buscar($hoy, now()->addDay()->toDateString())->assertOk();
        $this->buscar(now()->subDay()->toDateString(), $hoy)->assertStatus(422);
    }

    /**
     * Reserva existente del 10 al 13. Cada caso es la busqueda y si la habitacion queda libre.
     */
    public static function intervalos(): array
    {
        return [
            'identico' => ['2040-03-10', '2040-03-13', false],
            'contenido' => ['2040-03-11', '2040-03-12', false],
            'lo_contiene' => ['2040-03-09', '2040-03-14', false],
            'traslapa_el_inicio' => ['2040-03-08', '2040-03-11', false],
            'traslapa_el_final' => ['2040-03-12', '2040-03-15', false],
            'ultima_noche' => ['2040-03-12', '2040-03-13', false],
            'primera_noche' => ['2040-03-10', '2040-03-11', false],
            'sale_cuando_entra_la_otra' => ['2040-03-08', '2040-03-10', true],
            'entra_cuando_sale_la_otra' => ['2040-03-13', '2040-03-15', true],
            'totalmente_antes' => ['2040-03-01', '2040-03-05', true],
            'totalmente_despues' => ['2040-03-20', '2040-03-22', true],
        ];
    }

    #[DataProvider('intervalos')]
    public function test_los_intervalos_son_semiabiertos(string $llegada, string $salida, bool $libre)
    {
        $this->reserva($this->habitacion, '2040-03-10', '2040-03-13');

        $esperado = $libre ? ['D1'] : [];
        $this->assertSame($esperado, $this->numeros($this->buscar($llegada, $salida)->assertOk()));
    }

    public function test_solo_las_reservas_vigentes_ocupan()
    {
        $otra = $this->habitacion($this->tipo, 'D2');
        $this->reserva($this->habitacion, '2040-03-10', '2040-03-13', Reserva::CANCELADA);
        $this->reserva($otra, '2040-03-10', '2040-03-13', Reserva::FINALIZADA);
        $this->assertEqualsCanonicalizing(['D1', 'D2'], $this->numeros($this->buscar()));

        $this->reserva($this->habitacion, '2040-03-10', '2040-03-13', Reserva::PENDIENTE);
        $this->assertSame(['D2'], $this->numeros($this->buscar()));

        $this->reserva($otra, '2040-03-10', '2040-03-13', Reserva::EN_ESTADIA);
        $this->assertSame([], $this->numeros($this->buscar()));
    }

    public function test_una_pendiente_vencida_libera_la_habitacion()
    {
        $reserva = $this->reserva($this->habitacion, '2040-03-10', '2040-03-13', Reserva::PENDIENTE);
        $this->assertSame([], $this->numeros($this->buscar()));

        $horas = (int) Configuracion::valor('reserva_pendiente_horas');
        $reserva->forceFill(['created_at' => now()->subHours($horas)->subMinute()])->save();

        $this->assertSame(['D1'], $this->numeros($this->buscar()));
    }

    public function test_el_bloqueo_ocupa_hasta_su_ultimo_dia_inclusive()
    {
        BloqueoHabitacion::create([
            'habitacion_id' => $this->habitacion->id,
            'fecha_inicio' => '2040-03-10',
            'fecha_fin' => '2040-03-12',
            'motivo' => BloqueoHabitacion::MANTENIMIENTO,
        ]);

        $this->assertSame([], $this->numeros($this->buscar('2040-03-12', '2040-03-13')));
        $this->assertSame([], $this->numeros($this->buscar('2040-03-09', '2040-03-11')));
        $this->assertSame(['D1'], $this->numeros($this->buscar('2040-03-13', '2040-03-14')));
        $this->assertSame(['D1'], $this->numeros($this->buscar('2040-03-08', '2040-03-10')));
    }

    public function test_un_bloqueo_eliminado_no_cuenta()
    {
        BloqueoHabitacion::create([
            'habitacion_id' => $this->habitacion->id,
            'fecha_inicio' => '2040-03-10',
            'fecha_fin' => '2040-03-12',
            'motivo' => BloqueoHabitacion::USO_INTERNO,
            'estado' => 0,
        ]);

        $this->assertSame(['D1'], $this->numeros($this->buscar()));
    }

    public function test_excluye_mantenimiento_eliminadas_y_tipos_eliminados_pero_no_limpieza()
    {
        $this->habitacion($this->tipo, 'D2', ['estado_habitacion' => Habitacion::MANTENIMIENTO]);
        $this->habitacion($this->tipo, 'D3', ['estado' => 0]);
        $this->habitacion($this->tipo, 'D4', ['estado_habitacion' => Habitacion::LIMPIEZA]);
        $this->habitacion($this->tipo('Retirado', 2, 50)->fill(['estado' => 0]), 'D5')->tipoHabitacion->update(['estado' => 0]);

        $this->assertEqualsCanonicalizing(['D1', 'D4'], $this->numeros($this->buscar()));
    }

    public function test_una_reserva_sin_habitacion_asignada_ocupa_un_lugar_de_su_tipo()
    {
        $this->habitacion($this->tipo, 'D2');
        $this->reserva(null, '2040-03-10', '2040-03-13');

        $this->assertCount(1, $this->numeros($this->buscar()));

        $this->reserva(null, '2040-03-11', '2040-03-12');

        $this->assertCount(0, $this->numeros($this->buscar()));
        $this->assertCount(2, $this->numeros($this->buscar('2040-03-13', '2040-03-15')));
    }

    public function test_respeta_la_capacidad_y_el_tipo_pedido()
    {
        $otro = $this->tipo('Familiar grande', 5, 200);
        $this->habitacion($otro, 'F1');

        $this->assertSame(['F1'], $this->numeros($this->buscar('2040-03-10', '2040-03-13', ['huespedes' => 3])));
        $this->assertSame(['D1'], $this->numeros($this->buscar('2040-03-10', '2040-03-13', ['tipo_habitacion_id' => $this->tipo->id])));
    }

    public function test_los_filtros_acotan_sin_cambiar_las_opciones()
    {
        $otro = $this->tipo('Familiar grande', 5, 200);
        $this->habitacion($otro, 'F1');

        $res = $this->buscar('2040-03-10', '2040-03-13', ['precio_max' => 150])->assertOk();
        $this->assertSame(['D1'], $this->numeros($res));
        $this->assertEqualsCanonicalizing(
            TipoHabitacion::where('estado', 1)->pluck('id')->all(),
            array_column($res->json('filtros.tipos'), 'id')
        );
        $this->assertSame([2, 5], $res->json('filtros.capacidades'));
        $this->assertNull($res->json('alternativas'));

        $this->assertSame(['F1'], $this->numeros($this->buscar('2040-03-10', '2040-03-13', ['precio_min' => 150])));
        $this->assertSame(['F1'], $this->numeros($this->buscar('2040-03-10', '2040-03-13', ['capacidad' => 4])));
    }

    public function test_muestra_tarifa_total_y_ordena_del_mas_barato_al_mas_caro()
    {
        $this->habitacion($this->tipo('Economica', 2, 40), 'E1');

        $res = $this->buscar()->assertOk();

        $this->assertSame(['E1', 'D1'], $this->numeros($res));
        $res->assertJsonPath('data.0.precio_noche', 40)
            ->assertJsonPath('data.0.total', 120)
            ->assertJsonCount(3, 'data.0.noches')
            ->assertJsonPath('meta.noches', 3);
    }

    public function test_cada_noche_usa_su_temporada_y_su_tarifa()
    {
        //Noches del 10 y 11 sin temporada, 12 con multiplicador, 13 con tarifa propia
        Temporada::create(['nombre' => 'Solo multiplicador', 'fecha_inicio' => '2040-03-12', 'fecha_fin' => '2040-03-12', 'multiplicador' => 1.5]);
        $conTarifa = Temporada::create(['nombre' => 'Con tarifa', 'fecha_inicio' => '2040-03-13', 'fecha_fin' => '2040-03-20', 'multiplicador' => 3]);
        Tarifa::create(['tipo_habitacion_id' => $this->tipo->id, 'temporada_id' => $conTarifa->id, 'precio_noche' => 111]);

        $res = $this->buscar('2040-03-10', '2040-03-14')->assertOk();

        $this->assertSame([100, 100, 150, 111], array_column($res->json('data.0.noches'), 'precio_noche'));
        $this->assertSame([null, null, 'Solo multiplicador', 'Con tarifa'], array_column($res->json('data.0.noches'), 'temporada'));
        $res->assertJsonPath('data.0.total', 461)->assertJsonPath('data.0.precio_noche', 115.25);
    }

    public function test_la_tarifa_eliminada_vuelve_al_multiplicador_y_la_prioridad_decide()
    {
        $baja = Temporada::create(['nombre' => 'Baja', 'fecha_inicio' => '2040-03-01', 'fecha_fin' => '2040-03-31', 'multiplicador' => 2, 'prioridad' => 1]);
        Temporada::create(['nombre' => 'Alta', 'fecha_inicio' => '2040-03-10', 'fecha_fin' => '2040-03-10', 'multiplicador' => 1.1, 'prioridad' => 5]);
        Tarifa::create(['tipo_habitacion_id' => $this->tipo->id, 'temporada_id' => $baja->id, 'precio_noche' => 70, 'estado' => 0]);

        $res = $this->buscar('2040-03-10', '2040-03-12')->assertOk();

        $this->assertSame([110, 200], array_column($res->json('data.0.noches'), 'precio_noche'));
        $this->assertSame(['Alta', 'Baja'], array_column($res->json('data.0.noches'), 'temporada'));
    }

    public function test_el_dia_de_salida_no_se_cobra_con_la_temporada_que_empieza_ese_dia()
    {
        Temporada::create(['nombre' => 'Desde la salida', 'fecha_inicio' => '2040-03-12', 'fecha_fin' => '2040-03-20', 'multiplicador' => 5]);

        $this->buscar('2040-03-10', '2040-03-12')->assertOk()->assertJsonPath('data.0.total', 200);
    }

    public function test_sin_disponibilidad_sugiere_otros_tipos()
    {
        $otro = $this->tipo('Alternativa', 2, 60);
        $this->habitacion($otro, 'A1');
        $this->habitacion($this->tipo('Muy pequena', 1, 30), 'P1');
        $this->reserva($this->habitacion, '2040-03-10', '2040-03-13');

        $res = $this->buscar('2040-03-10', '2040-03-13', ['tipo_habitacion_id' => $this->tipo->id, 'huespedes' => 2])->assertOk();

        $this->assertSame([], $res->json('data'));
        $res->assertJsonCount(1, 'alternativas.tipos')
            ->assertJsonPath('alternativas.tipos.0.id', $otro->id)
            ->assertJsonPath('alternativas.tipos.0.total', 180);
    }

    public function test_sin_disponibilidad_sugiere_las_mismas_noches_en_fechas_cercanas()
    {
        $this->reserva($this->habitacion, '2040-03-10', '2040-03-13');

        $fechas = $this->buscar('2040-03-11', '2040-03-13')->assertOk()->json('alternativas.fechas');

        $this->assertCount(3, $fechas);
        foreach ($fechas as $fecha) {
            $this->assertTrue($fecha['salida'] <= '2040-03-10' || $fecha['llegada'] >= '2040-03-13', json_encode($fecha));
            $this->assertSame(2, (int) \Carbon\Carbon::parse($fecha['llegada'])->diffInDays($fecha['salida']));
        }
        $this->assertSame('2040-03-08', $fechas[0]['llegada']);
    }

    public function test_las_fechas_sugeridas_no_quedan_en_el_pasado()
    {
        $hoy = now()->toDateString();
        $this->reserva($this->habitacion, $hoy, now()->addDays(2)->toDateString());

        $fechas = $this->buscar($hoy, now()->addDays(2)->toDateString())->assertOk()->json('alternativas.fechas');

        $this->assertNotEmpty($fechas);
        foreach ($fechas as $fecha) {
            $this->assertGreaterThanOrEqual($hoy, $fecha['llegada']);
        }
    }

    public function test_si_hay_resultados_no_ofrece_alternativas_y_si_es_el_filtro_tampoco()
    {
        $this->assertNull($this->buscar()->json('alternativas'));

        $res = $this->buscar('2040-03-10', '2040-03-13', ['precio_max' => 1])->assertOk();
        $this->assertSame([], $res->json('data'));
        $this->assertNull($res->json('alternativas'));
    }
}
