<?php

namespace Tests\Feature\Api;

use App\Mail\ReservaCanceladaMail;
use App\Models\Configuracion;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Models\TipoHabitacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Mi cuenta: cada huesped ve y cancela solo sus reservas, con la politica configurada.
 */
class MiCuentaApiTest extends ApiTestCase
{
    private Habitacion $habitacion;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Configuracion::where('clave', 'cancelacion_limite_horas')->update(['valor' => '48']);
        Configuracion::where('clave', 'cancelacion_reembolso_porcentaje')->update(['valor' => '100']);
        Configuracion::where('clave', 'no_show_reembolso_porcentaje')->update(['valor' => '0']);
        config(['hotel.checkin' => '14:00']);

        $tipo = TipoHabitacion::create(['nombre' => 'Mi cuenta ' . uniqid(), 'capacidad' => 2, 'precio_base' => 80]);
        $this->habitacion = Habitacion::create(['numero' => 'M' . rand(100, 999), 'piso' => 1, 'tipo_habitacion_id' => $tipo->id]);
    }

    private function cuenta(string $nombre): User
    {
        $user = User::create(['name' => $nombre, 'email' => uniqid() . '@test.com', 'password' => Hash::make('x'), 'email_verified_at' => now()]);
        $user->assignRole('Huésped');

        return $user;
    }

    private function reservaDe(User $user, string $llegada, int $estado = Reserva::CONFIRMADA, array $extra = []): Reserva
    {
        return Reserva::create(array_merge([
            'codigo' => Reserva::generarCodigo(),
            'huesped_id' => Huesped::paraUsuario($user)->id,
            'tipo_habitacion_id' => $this->habitacion->tipo_habitacion_id,
            'habitacion_id' => $this->habitacion->id,
            'fecha_llegada' => $llegada,
            'fecha_salida' => Carbon::parse($llegada)->addDays(2)->toDateString(),
            'adultos' => 1,
            'estado_reserva' => $estado,
            'total' => 200,
            'anticipo_requerido' => 60,
        ], $extra));
    }

    public function test_exige_sesion()
    {
        $this->getJson('/api/mi-cuenta/reservas')->assertUnauthorized();
        $this->postJson('/api/mi-cuenta/reservas/HL-X/cancelar')->assertUnauthorized();
    }

    public function test_solo_ve_sus_reservas()
    {
        $ana = $this->cuenta('Ana');
        $beto = $this->cuenta('Beto');
        $deAna = $this->reservaDe($ana, '2041-03-01');
        $deBeto = $this->reservaDe($beto, '2041-04-01');

        $codigos = array_column($this->como($ana)->getJson('/api/mi-cuenta/reservas')->assertOk()->json('data'), 'codigo');
        $this->assertSame([$deAna->codigo], $codigos);

        $this->como($ana)->getJson("/api/mi-cuenta/reservas/{$deBeto->codigo}")->assertNotFound();
        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$deBeto->codigo}/cancelar")->assertNotFound();
        $this->assertSame(Reserva::CONFIRMADA, $deBeto->fresh()->estado_reserva);
    }

    public function test_una_ficha_con_el_mismo_correo_pero_sin_cuenta_no_aparece()
    {
        $ana = $this->cuenta('Ana');
        $fichaSuelta = Huesped::create(['nombres' => 'Ana', 'apellidos' => 'Telefónica', 'email' => $ana->email]);
        $this->reservaDe($ana, '2041-03-01', Reserva::CONFIRMADA, ['huesped_id' => $fichaSuelta->id]);

        $this->assertSame([], $this->como($ana)->getJson('/api/mi-cuenta/reservas')->json('data'));
    }

    public function test_el_detalle_trae_noches_servicios_historial_y_politica()
    {
        $ana = $this->cuenta('Ana');
        $reserva = $this->reservaDe($ana, '2041-03-01');

        $res = $this->como($ana)->getJson("/api/mi-cuenta/reservas/{$reserva->codigo}")->assertOk();

        $res->assertJsonPath('data.estado_nombre', 'Confirmada')
            ->assertJsonPath('data.cancelacion.permitida', true)
            ->assertJsonPath('data.cancelacion.en_plazo', true)
            ->assertJsonPath('data.cancelacion.reembolso', 60)
            ->assertJsonStructure(['data' => ['detalle_noches', 'servicios', 'historial', 'huesped']]);
    }

    public function test_cancelar_en_plazo_reembolsa_el_anticipo_y_avisa_por_correo()
    {
        $ana = $this->cuenta('Ana');
        $reserva = $this->reservaDe($ana, '2041-03-01');

        $this->como($ana)
            ->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/cancelar", ['motivo' => 'Cambio de planes'])
            ->assertOk()
            ->assertJsonPath('data.estado_nombre', 'Cancelada');

        $reserva->refresh();
        $this->assertSame(Reserva::CANCELADA, $reserva->estado_reserva);
        $this->assertNotNull($reserva->cancelada_at);
        $this->assertSame('Cambio de planes', $reserva->motivo_cancelacion);

        $ultima = $reserva->historial()->reorder('id', 'desc')->first();
        $this->assertSame($ana->id, $ultima->user_id);
        $this->assertStringContainsString('100 % del anticipo pagado ($60.00)', $ultima->comentario);

        Mail::assertQueued(ReservaCanceladaMail::class, fn ($m) => $m->hasTo($reserva->huesped->email) && $m->politica['reembolso'] === 60.0);
    }

    public function test_si_el_correo_falla_la_cancelacion_igual_queda_guardada()
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caido'));
        $ana = $this->cuenta('Ana');
        $reserva = $this->reservaDe($ana, '2041-03-01');

        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/cancelar")->assertOk();

        $this->assertSame(Reserva::CANCELADA, $reserva->fresh()->estado_reserva);
    }

    public function test_el_limite_se_cuenta_hasta_la_hora_de_entrada()
    {
        $ana = $this->cuenta('Ana');
        $reserva = $this->reservaDe($ana, '2041-03-10');

        //48 horas antes de las 14:00 del 10 de marzo: 8 de marzo a las 14:00
        $this->travelTo(Carbon::parse('2041-03-08 13:59'));
        $this->assertTrue(app(\App\Services\CancelacionService::class)->evaluar($reserva)['en_plazo']);

        $this->travelTo(Carbon::parse('2041-03-08 14:00'));
        $politica = app(\App\Services\CancelacionService::class)->evaluar($reserva);
        $this->assertFalse($politica['en_plazo']);
        $this->assertSame(0.0, $politica['reembolso']);
    }

    public function test_cancelar_fuera_de_plazo_no_reembolsa()
    {
        $ana = $this->cuenta('Ana');
        $reserva = $this->reservaDe($ana, now()->addDay()->toDateString());

        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/cancelar")->assertOk();

        $this->assertStringContainsString('0 % del anticipo pagado ($0.00)', $reserva->historial()->reorder('id', 'desc')->first()->comentario);
        Mail::assertQueued(ReservaCanceladaMail::class, fn ($m) => $m->politica['reembolso'] === 0.0);
    }

    public function test_una_pendiente_no_tiene_anticipo_que_devolver()
    {
        $ana = $this->cuenta('Ana');
        $reserva = $this->reservaDe($ana, '2041-03-01', Reserva::PENDIENTE);

        $this->como($ana)->getJson("/api/mi-cuenta/reservas/{$reserva->codigo}")
            ->assertJsonPath('data.cancelacion.anticipo_pagado', 0)
            ->assertJsonPath('data.cancelacion.reembolso', 0);

        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/cancelar")->assertOk();

        $this->assertStringContainsString('Sin anticipo pagado', $reserva->historial()->reorder('id', 'desc')->first()->comentario);
    }

    public function test_cancelar_libera_la_habitacion()
    {
        $ana = $this->cuenta('Ana');
        $reserva = $this->reservaDe($ana, '2041-03-01');
        $buscar = '/api/disponibilidad?llegada=2041-03-01&salida=2041-03-03&huespedes=1&tipo_habitacion_id=' . $this->habitacion->tipo_habitacion_id;

        $this->assertSame([], $this->getJson($buscar)->json('data'));

        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/cancelar")->assertOk();

        $this->assertCount(1, $this->getJson($buscar)->json('data'));
    }

    public function test_no_cancela_dos_veces_ni_estadias_comenzadas_ni_despues_de_la_hora_de_entrada()
    {
        $ana = $this->cuenta('Ana');

        $cancelada = $this->reservaDe($ana, '2041-03-01');
        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$cancelada->codigo}/cancelar")->assertOk();
        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$cancelada->codigo}/cancelar")
            ->assertStatus(422)
            ->assertJsonPath('errors.reserva.0', 'La reserva ya está cancelada.');
        Mail::assertQueued(ReservaCanceladaMail::class, 1);

        $enCurso = $this->reservaDe($ana, '2041-05-01', Reserva::EN_ESTADIA);
        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$enCurso->codigo}/cancelar")->assertStatus(422);

        $hoy = $this->reservaDe($ana, '2041-06-01');
        $this->travelTo(Carbon::parse('2041-06-01 14:30'));
        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$hoy->codigo}/cancelar")->assertStatus(422);
        $this->assertSame(Reserva::CONFIRMADA, $hoy->fresh()->estado_reserva);
    }

    public function test_una_pendiente_vencida_se_marca_como_tal()
    {
        $ana = $this->cuenta('Ana');
        $reserva = $this->reservaDe($ana, '2041-03-01', Reserva::PENDIENTE);
        $reserva->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->como($ana)->getJson('/api/mi-cuenta/reservas')
            ->assertJsonPath('data.0.vencida', true)
            ->assertJsonPath('data.0.estado_nombre', 'Pendiente vencida');
    }

    public function test_la_ficha_precarga_la_pantalla_de_reserva()
    {
        $this->como($this->huesped())
            ->getJson('/api/mi-cuenta/huesped')
            ->assertOk()
            ->assertJsonPath('data.email', 'huesped@hotellink.com');
    }
}
