<?php

namespace Tests\Feature\Api;

use App\Models\BloqueoHabitacion;
use App\Models\Habitacion;
use App\Models\Reserva;

class BloqueosApiTest extends ApiTestCase
{
    private function habitacion(): Habitacion
    {
        return Habitacion::create([
            'numero' => 'B' . rand(100, 999),
            'piso' => 1,
            'tipo_habitacion_id' => \App\Models\TipoHabitacion::firstOrFail()->id,
        ]);
    }

    private function fecha(int $dias): string
    {
        return now()->addDays($dias)->toDateString();
    }

    private function datos(Habitacion $habitacion, int $inicio = 10, int $fin = 12): array
    {
        return [
            'habitacion_id' => $habitacion->id,
            'fecha_inicio' => $this->fecha($inicio),
            'fecha_fin' => $this->fecha($fin),
            'motivo' => BloqueoHabitacion::MANTENIMIENTO,
            'descripcion' => 'Pintura',
        ];
    }

    private function reserva(Habitacion $habitacion, int $llegada, int $salida, int $estado = Reserva::CONFIRMADA): Reserva
    {
        return Reserva::create([
            'codigo' => 'HL-B-' . uniqid(),
            'huesped_id' => Reserva::firstOrFail()->huesped_id,
            'tipo_habitacion_id' => $habitacion->tipo_habitacion_id,
            'habitacion_id' => $habitacion->id,
            'fecha_llegada' => $this->fecha($llegada),
            'fecha_salida' => $this->fecha($salida),
            'adultos' => 1,
            'estado_reserva' => $estado,
        ]);
    }

    public function test_recepcion_no_administra_bloqueos()
    {
        $this->getJson('/api/admin/bloqueos')->assertUnauthorized();
        $this->como($this->recepcion())->getJson('/api/admin/bloqueos')->assertForbidden();
        $this->como($this->recepcion())->postJson('/api/admin/bloqueos', $this->datos($this->habitacion()))->assertForbidden();
    }

    public function test_crear_guarda_quien_lo_registro()
    {
        $habitacion = $this->habitacion();
        $admin = $this->admin();

        $res = $this->como($admin)->postJson('/api/admin/bloqueos', $this->datos($habitacion))->assertCreated();

        $this->assertSame($habitacion->numero, $res->json('data.habitacion'));
        $this->assertSame('Mantenimiento', $res->json('data.motivo_nombre'));
        $this->assertSame($this->fecha(10), $res->json('data.fecha_inicio'));
        $this->assertDatabaseHas('bloqueos_habitacion', ['habitacion_id' => $habitacion->id, 'user_id' => $admin->id]);
    }

    public function test_valida_fechas_motivo_y_habitacion()
    {
        $this->como($this->admin())
            ->postJson('/api/admin/bloqueos', [
                'habitacion_id' => 999999,
                'fecha_inicio' => $this->fecha(5),
                'fecha_fin' => $this->fecha(4),
                'motivo' => 'vacaciones',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['habitacion_id', 'fecha_fin', 'motivo']);
    }

    public function test_un_bloqueo_de_un_solo_dia_es_valido()
    {
        $this->como($this->admin())
            ->postJson('/api/admin/bloqueos', $this->datos($this->habitacion(), 10, 10))
            ->assertCreated();
    }

    public function test_no_permite_traslapar_otro_bloqueo_de_la_misma_habitacion()
    {
        $habitacion = $this->habitacion();
        $this->como($this->admin())->postJson('/api/admin/bloqueos', $this->datos($habitacion, 10, 12))->assertCreated();

        //fecha_fin es inclusive: el dia 12 sigue bloqueado
        $this->como($this->admin())
            ->postJson('/api/admin/bloqueos', $this->datos($habitacion, 12, 14))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_inicio']);

        $this->como($this->admin())->postJson('/api/admin/bloqueos', $this->datos($habitacion, 13, 14))->assertCreated();
        $this->como($this->admin())->postJson('/api/admin/bloqueos', $this->datos($this->habitacion(), 10, 12))->assertCreated();
    }

    public function test_no_permite_bloquear_sobre_una_reserva_vigente()
    {
        $habitacion = $this->habitacion();
        $reserva = $this->reserva($habitacion, 10, 12);

        $res = $this->como($this->admin())
            ->postJson('/api/admin/bloqueos', $this->datos($habitacion, 11, 11))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_inicio']);

        $this->assertStringContainsString($reserva->codigo, $res->json('errors.fecha_inicio.0'));
    }

    public function test_el_dia_de_salida_de_una_reserva_si_se_puede_bloquear()
    {
        $habitacion = $this->habitacion();
        $this->reserva($habitacion, 10, 12);

        $this->como($this->admin())->postJson('/api/admin/bloqueos', $this->datos($habitacion, 12, 13))->assertCreated();
    }

    public function test_una_reserva_cancelada_no_impide_bloquear()
    {
        $habitacion = $this->habitacion();
        $this->reserva($habitacion, 10, 12, Reserva::CANCELADA);

        $this->como($this->admin())->postJson('/api/admin/bloqueos', $this->datos($habitacion, 10, 12))->assertCreated();
    }

    public function test_editar_no_choca_consigo_mismo_y_eliminar_es_logico()
    {
        $habitacion = $this->habitacion();
        $id = $this->como($this->admin())
            ->postJson('/api/admin/bloqueos', $this->datos($habitacion, 10, 12))
            ->json('data.id');

        $this->como($this->admin())->putJson("/api/admin/bloqueos/$id", $this->datos($habitacion, 10, 15))->assertOk();
        $this->assertSame($this->fecha(15), BloqueoHabitacion::find($id)->fecha_fin->toDateString());

        $this->como($this->admin())->deleteJson("/api/admin/bloqueos/$id")->assertOk();
        $this->assertDatabaseHas('bloqueos_habitacion', ['id' => $id, 'estado' => 0]);

        //Un bloqueo eliminado libera sus fechas
        $this->como($this->admin())->postJson('/api/admin/bloqueos', $this->datos($habitacion, 10, 12))->assertCreated();
    }

    public function test_el_listado_oculta_los_terminados_salvo_que_se_pidan()
    {
        $habitacion = $this->habitacion();
        BloqueoHabitacion::create($this->datos($habitacion, -5, -3));
        BloqueoHabitacion::create($this->datos($habitacion, 3, 4));

        $url = "/api/admin/bloqueos?habitacion_id={$habitacion->id}";

        $this->assertCount(1, $this->como($this->admin())->getJson($url)->assertOk()->json('data'));
        $this->assertCount(2, $this->como($this->admin())->getJson($url . '&terminados=1')->assertOk()->json('data'));
    }
}
