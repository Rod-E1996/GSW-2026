<?php

namespace Tests\Feature\Api;

use App\Mail\ReservaConfirmadaMail;
use App\Mail\ReservaModificadaMail;
use App\Mail\ReservaRegistradaMail;
use App\Models\Comprobante;
use App\Models\Configuracion;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\TipoHabitacion;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Fase 6: operacion de recepcion de la reserva al check-out, con permisos del servidor.
 */
class RecepcionApiTest extends ApiTestCase
{
    private TipoHabitacion $tipo;
    private Habitacion $hab1;
    private Habitacion $hab2;
    private Huesped $titular;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2041-03-01 09:00'));

        Configuracion::where('clave', 'anticipo_porcentaje')->update(['valor' => '30']);
        Configuracion::where('clave', 'impuesto_porcentaje')->update(['valor' => '13']);
        Configuracion::where('clave', 'reserva_pendiente_horas')->update(['valor' => '24']);
        Configuracion::where('clave', 'cancelacion_reembolso_porcentaje')->update(['valor' => '100']);
        Configuracion::where('clave', 'no_show_reembolso_porcentaje')->update(['valor' => '0']);

        $this->tipo = TipoHabitacion::create(['nombre' => 'Recepción ' . uniqid(), 'capacidad' => 2, 'precio_base' => 100]);
        $this->hab1 = Habitacion::create(['numero' => 'R' . rand(100, 499), 'piso' => 1, 'tipo_habitacion_id' => $this->tipo->id]);
        $this->hab2 = Habitacion::create(['numero' => 'R' . rand(500, 999), 'piso' => 1, 'tipo_habitacion_id' => $this->tipo->id]);
        $this->titular = Huesped::create([
            'nombres' => 'Marta', 'apellidos' => 'Quintanilla', 'tipo_documento' => 'DUI',
            'documento' => '0' . rand(1000000, 9999999) . '-1', 'email' => uniqid() . '@huesped.test', 'telefono' => '7' . rand(1000000, 9999999),
        ]);
    }

    private function nueva(array $cambios = []): \Illuminate\Testing\TestResponse
    {
        return $this->como($this->recepcion())->postJson('/api/admin/reservas', $cambios + [
            'huesped_id' => $this->titular->id,
            'habitacion_id' => $this->hab1->id,
            'fecha_llegada' => '2041-03-01',
            'fecha_salida' => '2041-03-03',
            'adultos' => 2,
            'canal' => 'telefono',
        ]);
    }

    private function crearYConfirmar(array $cambios = []): string
    {
        $creada = $this->nueva($cambios)->assertCreated();
        $codigo = $creada->json('data.codigo');
        $this->como($this->recepcion())->postJson("/api/admin/reservas/$codigo/confirmar", ['monto' => $creada->json('data.anticipo_requerido'), 'metodo_pago' => 'efectivo'])
            ->assertOk();

        return $codigo;
    }

    public function test_solo_el_personal_con_permiso_opera_reservas()
    {
        $this->getJson('/api/admin/reservas')->assertUnauthorized();
        $this->como($this->huesped())->getJson('/api/admin/reservas')->assertForbidden();
        $this->como($this->huesped())->postJson('/api/admin/reservas', [])->assertForbidden();
        $this->como($this->huesped())->getJson('/api/admin/panel')->assertForbidden();
        $this->como($this->huesped())->getJson('/api/admin/huespedes?q=ma')->assertForbidden();

        $this->como($this->recepcion())->getJson('/api/admin/reservas')->assertOk();
        $this->como($this->admin())->getJson('/api/admin/reservas')->assertOk();
    }

    public function test_busca_por_codigo_documento_correo_nombre_y_qr()
    {
        $codigo = $this->crearYConfirmar();
        $token = Reserva::where('codigo', $codigo)->first()->comprobanteVigente()->value('qr_token');
        $rec = $this->como($this->recepcion());

        foreach ([strtolower($codigo), $this->titular->documento, $this->titular->email, 'marta quint', "http://x/panel/verificar/$token"] as $q) {
            $codigos = array_column($rec->getJson('/api/admin/reservas?q=' . urlencode($q))->assertOk()->json('data'), 'codigo');
            $this->assertSame([$codigo], $codigos, "Busqueda: $q");
        }

        $this->assertSame([], $rec->getJson('/api/admin/reservas?q=noexiste')->json('data'));
        $this->assertCount(1, $rec->getJson('/api/admin/reservas?estado=2&q=' . $codigo)->json('data'));
        $this->assertCount(0, $rec->getJson('/api/admin/reservas?estado=1&q=' . $codigo)->json('data'));
    }

    public function test_el_filtro_distingue_las_pendientes_vencidas()
    {
        $codigo = $this->nueva()->json('data.codigo');
        Reserva::where('codigo', $codigo)->first()->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->como($this->recepcion())->getJson("/api/admin/reservas?estado=vencida&q=$codigo")
            ->assertJsonPath('data.0.estado_nombre', 'Pendiente vencida');
        $this->assertSame([], $this->como($this->recepcion())->getJson("/api/admin/reservas?estado=1&q=$codigo")->json('data'));
    }

    public function test_busca_y_registra_huespedes_sin_duplicar_documentos()
    {
        $rec = $this->como($this->recepcion());

        $rec->getJson('/api/admin/huespedes?q=' . urlencode($this->titular->documento))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->titular->id)
            ->assertJsonPath('data.0.tiene_cuenta', false);

        $rec->postJson('/api/admin/huespedes', ['nombres' => 'Luis', 'apellidos' => 'Pérez', 'tipo_documento' => 'Pasaporte', 'documento' => 'X123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'telefono']);

        $id = $rec->postJson('/api/admin/huespedes', ['nombres' => 'Luis', 'apellidos' => 'Pérez', 'tipo_documento' => 'Pasaporte', 'documento' => 'X123', 'telefono' => '22223333'])
            ->assertCreated()
            ->json('data.id');

        $rec->postJson('/api/admin/huespedes', ['nombres' => 'Otro', 'apellidos' => 'Nombre', 'tipo_documento' => 'Pasaporte', 'documento' => 'X123', 'telefono' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('existente.id', $id);
    }

    public function test_reserva_telefonica_queda_pendiente_y_avisa_al_huesped()
    {
        $res = $this->nueva(['servicios' => [['servicio_id' => $this->servicio()->id, 'cantidad' => 2]]])->assertCreated();

        $res->assertJsonPath('data.estado_nombre', 'Pendiente')
            ->assertJsonPath('data.canal_nombre', 'Teléfono')
            ->assertJsonPath('data.creada_por', $this->recepcion()->name)
            ->assertJsonPath('data.acciones.confirmar', true)
            ->assertJsonPath('data.acciones.checkin', false);

        $this->assertEquals(244.08, $res->json('data.total')); //(200 + 2 x 8) x 1.13
        Mail::assertSent(ReservaRegistradaMail::class, fn ($m) => $m->hasTo($this->titular->email));
    }

    public function test_reserva_telefonica_respeta_disponibilidad_y_capacidad()
    {
        $this->nueva()->assertCreated();
        $this->nueva()->assertStatus(409)->assertJsonStructure(['alternativas' => ['tipos', 'fechas']]);
        $this->nueva(['habitacion_id' => $this->hab2->id, 'adultos' => 3])->assertStatus(422)->assertJsonValidationErrors('adultos');
        $this->nueva(['canal' => 'web', 'habitacion_id' => $this->hab2->id])->assertStatus(422)->assertJsonValidationErrors('canal');
    }

    public function test_confirmar_registra_el_anticipo_emite_comprobante_y_avisa()
    {
        $codigo = $this->nueva()->json('data.codigo');
        $rec = $this->como($this->recepcion());

        $rec->postJson("/api/admin/reservas/$codigo/confirmar", ['monto' => 10, 'metodo_pago' => 'efectivo'])
            ->assertStatus(422)->assertJsonValidationErrors('monto');

        $res = $rec->postJson("/api/admin/reservas/$codigo/confirmar", ['monto' => 67.80, 'metodo_pago' => 'transferencia', 'referencia' => 'TRF-001'])
            ->assertOk()
            ->assertJsonPath('data.estado_nombre', 'Confirmada')
            ->assertJsonPath('data.pagos.0.metodo', 'Transferencia')
            ->assertJsonPath('data.pagos.0.referencia', 'TRF-001')
            ->assertJsonPath('data.pagos.0.registrado_por', $this->recepcion()->name)
            ->assertJsonPath('data.comprobante.vigente', true);
        $this->assertEquals(158.2, $res->json('data.saldo'));

        $this->assertStringContainsString('Confirmada en recepción con anticipo de $67.80 (Transferencia)', Reserva::where('codigo', $codigo)->first()->historial()->reorder('id', 'desc')->first()->comentario);
        Mail::assertSent(ReservaConfirmadaMail::class);

        $rec->postJson("/api/admin/reservas/$codigo/confirmar", ['monto' => 67.80, 'metodo_pago' => 'efectivo'])->assertStatus(422);

        //La misma transferencia no se registra dos veces
        $otra = $this->nueva(['habitacion_id' => $this->hab2->id])->json('data.codigo');
        $rec->postJson("/api/admin/reservas/$otra/confirmar", ['monto' => 67.80, 'metodo_pago' => 'transferencia', 'referencia' => 'TRF-001'])
            ->assertStatus(422)->assertJsonValidationErrors('referencia');
    }

    public function test_modificar_revalida_recalcula_reemite_y_avisa()
    {
        $codigo = $this->crearYConfirmar();
        $rec = $this->como($this->recepcion());
        $antes = Reserva::where('codigo', $codigo)->first()->comprobanteVigente()->first();

        //Otra reserva ocupa la habitacion 2 el 4 de marzo
        app(ReservaService::class)->crear([
            'habitacion_id' => $this->hab2->id, 'huesped_id' => $this->titular->id,
            'fecha_llegada' => '2041-03-04', 'fecha_salida' => '2041-03-05', 'adultos' => 1,
        ]);

        //Alargar una noche en la misma habitacion: su propia reserva no le estorba
        $res = $rec->putJson("/api/admin/reservas/$codigo", [
            'habitacion_id' => $this->hab1->id, 'fecha_llegada' => '2041-03-01', 'fecha_salida' => '2041-03-04', 'adultos' => 2, 'notas' => 'Cuna',
        ])->assertOk();
        $this->assertEquals(339, $res->json('data.total'));
        $this->assertCount(3, $res->json('data.detalle_noches'));

        $reserva = Reserva::where('codigo', $codigo)->first();
        $this->assertStringContainsString('Salida 03/03/2041 → 04/03/2041', $reserva->historial()->reorder('id', 'desc')->first()->comentario);
        $this->assertSame(Comprobante::SUSTITUIDO, (int) $antes->fresh()->estado);
        $this->assertSame(2, (int) $reserva->comprobanteVigente()->value('version'));
        Mail::assertSent(ReservaModificadaMail::class, fn ($m) => collect($m->cambios)->contains('campo', 'Salida'));

        //Pasarla a la habitacion 2 choca con la otra reserva
        $rec->putJson("/api/admin/reservas/$codigo", [
            'habitacion_id' => $this->hab2->id, 'fecha_llegada' => '2041-03-01', 'fecha_salida' => '2041-03-05', 'adultos' => 2,
        ])->assertStatus(409);
        $this->assertSame($this->hab1->id, Reserva::where('codigo', $codigo)->value('habitacion_id'));

        //Las habitaciones ofrecidas incluyen la actual
        $ofrecidas = $rec->getJson("/api/admin/reservas/$codigo/habitaciones?llegada=2041-03-01&salida=2041-03-04&huespedes=2")->json('data');
        $this->assertTrue(collect($ofrecidas)->contains(fn ($h) => $h['id'] === $this->hab1->id && $h['actual']));

        $rec->putJson("/api/admin/reservas/$codigo", [
            'habitacion_id' => $this->hab1->id, 'fecha_llegada' => '2041-03-01', 'fecha_salida' => '2041-03-04', 'adultos' => 2, 'notas' => 'Cuna',
        ])->assertOk()->assertJsonPath('message', 'No había cambios que guardar.');
    }

    public function test_recepcion_cancela_un_no_show_con_motivo()
    {
        $codigo = $this->crearYConfirmar();
        $this->travelTo(Carbon::parse('2041-03-01 22:00'));
        $rec = $this->como($this->recepcion());

        $rec->postJson("/api/admin/reservas/$codigo/cancelar", [])->assertStatus(422)->assertJsonValidationErrors('motivo');
        $rec->postJson("/api/admin/reservas/$codigo/cancelar", ['motivo' => 'No se presentó'])
            ->assertOk()
            ->assertJsonPath('data.estado_nombre', 'Cancelada');

        $comentario = Reserva::where('codigo', $codigo)->first()->historial()->reorder('id', 'desc')->first()->comentario;
        $this->assertStringContainsString('Cancelada en recepción. Reembolso: 0 %', $comentario);
    }

    public function test_checkin_exige_confirmacion_fecha_habitacion_lista_e_identidad()
    {
        $rec = $this->como($this->recepcion());
        $pendiente = $this->nueva(['habitacion_id' => $this->hab2->id])->json('data.codigo');
        $rec->postJson("/api/admin/reservas/$pendiente/checkin", ['identidad_verificada' => true])
            ->assertStatus(422)->assertJsonPath('errors.reserva.0', 'La reserva está pendiente: confirma primero el anticipo.');

        $futura = $this->crearYConfirmar(['fecha_llegada' => '2041-03-10', 'fecha_salida' => '2041-03-11']);
        $rec->postJson("/api/admin/reservas/$futura/checkin", ['identidad_verificada' => true])->assertStatus(422);

        $codigo = $this->crearYConfirmar();
        $rec->postJson("/api/admin/reservas/$codigo/checkin", [])->assertStatus(422)->assertJsonValidationErrors('identidad_verificada');

        $this->hab1->update(['estado_habitacion' => Habitacion::LIMPIEZA]);
        $rec->postJson("/api/admin/reservas/$codigo/checkin", ['identidad_verificada' => true])
            ->assertStatus(422)->assertJsonValidationErrors('habitacion_id');

        //La habitacion 2 la tiene la pendiente: tampoco se puede usar
        $rec->postJson("/api/admin/reservas/$codigo/checkin", ['identidad_verificada' => true, 'habitacion_id' => $this->hab2->id])
            ->assertStatus(422)->assertJsonValidationErrors('habitacion_id');

        $this->hab1->update(['estado_habitacion' => Habitacion::DISPONIBLE]);
        $rec->postJson("/api/admin/reservas/$codigo/checkin", ['identidad_verificada' => true])
            ->assertOk()
            ->assertJsonPath('data.estado_nombre', 'En estadía')
            ->assertJsonPath('data.acciones.checkout', true);

        $reserva = Reserva::where('codigo', $codigo)->first();
        $this->assertNotNull($reserva->check_in_at);
        $this->assertNotNull($reserva->comprobanteVigente()->value('verificado_at'));
    }

    public function test_checkin_puede_cambiar_a_otra_habitacion_libre()
    {
        $codigo = $this->crearYConfirmar();
        $this->hab1->update(['estado_habitacion' => Habitacion::LIMPIEZA]);

        $this->como($this->recepcion())->postJson("/api/admin/reservas/$codigo/checkin", ['identidad_verificada' => true, 'habitacion_id' => $this->hab2->id])
            ->assertOk()
            ->assertJsonPath('data.habitacion', $this->hab2->numero);

        $reserva = Reserva::where('codigo', $codigo)->first();
        $this->assertSame($this->hab2->id, (int) $reserva->noches()->value('habitacion_id'));
        $this->assertStringContainsString("(antes {$this->hab1->numero})", $reserva->historial()->reorder('id', 'desc')->first()->comentario);
    }

    public function test_consumos_recalculan_el_total_y_solo_se_quitan_los_cargados()
    {
        $servicio = $this->servicio();
        $codigo = $this->crearYConfirmar(['servicios' => [['servicio_id' => $servicio->id, 'cantidad' => 1]]]);
        $rec = $this->como($this->recepcion());

        $rec->postJson("/api/admin/reservas/$codigo/consumos", ['servicio_id' => $servicio->id, 'cantidad' => 1])
            ->assertStatus(422); //aun no esta en estadia

        $rec->postJson("/api/admin/reservas/$codigo/checkin", ['identidad_verificada' => true])->assertOk();
        $res = $rec->postJson("/api/admin/reservas/$codigo/consumos", ['servicio_id' => $servicio->id, 'cantidad' => 3, 'notas' => 'Ropa de playa'])
            ->assertOk();

        $this->assertEquals(262.16, $res->json('data.total')); //(200 noches + 8 contratado + 24 consumo) x 1.13
        $consumo = collect($res->json('data.servicios'))->firstWhere('consumo', true);
        $contratado = collect($res->json('data.servicios'))->firstWhere('consumo', false);
        $this->assertSame(3, $consumo['cantidad']);
        $this->assertSame('Ropa de playa', $consumo['notas']);

        $rec->deleteJson("/api/admin/reservas/$codigo/consumos/{$contratado['linea']}")->assertStatus(422);
        $rec->deleteJson("/api/admin/reservas/$codigo/consumos/{$consumo['linea']}")
            ->assertOk()
            ->assertJsonPath('data.total', 235.04);

        $this->assertStringContainsString('Consumo quitado', Reserva::where('codigo', $codigo)->first()->historial()->reorder('id', 'desc')->first()->comentario);
    }

    public function test_checkout_cobra_el_saldo_exacto_y_libera_la_habitacion()
    {
        $codigo = $this->crearYConfirmar();
        $rec = $this->como($this->recepcion());
        $rec->postJson("/api/admin/reservas/$codigo/checkin", ['identidad_verificada' => true])->assertOk();

        $rec->postJson("/api/admin/reservas/$codigo/checkout", [])->assertStatus(422)->assertJsonValidationErrors('monto');
        $rec->postJson("/api/admin/reservas/$codigo/checkout", ['monto' => 100, 'metodo_pago' => 'tarjeta'])->assertStatus(422);

        $this->travelTo(Carbon::parse('2041-03-03 11:30'));
        $rec->postJson("/api/admin/reservas/$codigo/checkout", ['monto' => 158.20, 'metodo_pago' => 'tarjeta'])
            ->assertOk()
            ->assertJsonPath('data.estado_nombre', 'Finalizada')
            ->assertJsonPath('data.saldo', 0)
            ->assertJsonPath('data.pagos.1.tipo', Pago::SALDO);

        $this->assertSame(Habitacion::LIMPIEZA, $this->hab1->fresh()->estado_habitacion);
        $this->assertNotNull(Reserva::where('codigo', $codigo)->value('check_out_at'));
        $rec->postJson("/api/admin/reservas/$codigo/checkout", [])->assertStatus(422);
    }

    public function test_un_ciclo_completo_de_la_reserva_a_la_salida()
    {
        $rec = $this->como($this->recepcion());

        //Huesped nuevo que llega sin reserva
        $huesped = $rec->postJson('/api/admin/huespedes', [
            'nombres' => 'Carlos', 'apellidos' => 'Mejía', 'tipo_documento' => 'DUI', 'documento' => '09999999-9', 'email' => 'carlos@huesped.test',
        ])->assertCreated()->json('data.id');

        $codigo = $rec->postJson('/api/admin/reservas', [
            'huesped_id' => $huesped, 'habitacion_id' => $this->hab2->id, 'fecha_llegada' => '2041-03-01',
            'fecha_salida' => '2041-03-02', 'adultos' => 1, 'canal' => 'presencial',
        ])->assertCreated()->json('data.codigo');

        $rec->postJson("/api/admin/reservas/$codigo/confirmar", ['monto' => 113, 'metodo_pago' => 'efectivo'])->assertOk()
            ->assertJsonPath('data.saldo', 0);
        $rec->postJson("/api/admin/reservas/$codigo/checkin", ['identidad_verificada' => true])->assertOk();
        $rec->postJson("/api/admin/reservas/$codigo/consumos", ['servicio_id' => $this->servicio()->id, 'cantidad' => 1])->assertOk()
            ->assertJsonPath('data.saldo', 9.04);
        $rec->postJson("/api/admin/reservas/$codigo/checkout", ['monto' => 9.04, 'metodo_pago' => 'efectivo'])->assertOk()
            ->assertJsonPath('data.estado_nombre', 'Finalizada');

        $estados = Reserva::where('codigo', $codigo)->first()->historial()->where('estado_anterior', '!=', \DB::raw('estado_nuevo'))->pluck('estado_nuevo')->all();
        $this->assertSame([Reserva::CONFIRMADA, Reserva::EN_ESTADIA, Reserva::FINALIZADA], $estados);
        $this->assertSame(['registro', 'confirmacion'], Notificacion::whereHas('reserva', fn ($q) => $q->where('codigo', $codigo))->pluck('tipo')->all());
    }

    public function test_panel_de_control_y_calendario()
    {
        $hoy = $this->crearYConfirmar();
        $this->como($this->recepcion())->postJson("/api/admin/reservas/$hoy/checkin", ['identidad_verificada' => true])->assertOk();
        $manana = $this->crearYConfirmar(['habitacion_id' => $this->hab2->id, 'fecha_llegada' => '2041-03-02', 'fecha_salida' => '2041-03-04']);

        $panel = $this->como($this->recepcion())->getJson('/api/admin/panel')->assertOk();
        $this->assertGreaterThanOrEqual(1, $panel->json('indicadores.ocupadas'));
        $this->assertGreaterThanOrEqual(1, $panel->json('indicadores.en_estadia'));
        $this->assertEquals(135.6, $panel->json('indicadores.ingresos_mes'));
        $this->assertContains($hoy, array_column($panel->json('entradas'), 'codigo'));
        $this->assertContains($manana, array_column($panel->json('proximas'), 'codigo'));
        $this->assertContains($manana, array_column($panel->json('recientes'), 'codigo'));

        $cal = $this->como($this->recepcion())->getJson('/api/admin/calendario?mes=2041-03')->assertOk();
        $cal->assertJsonPath('dias', 31)->assertJsonPath('mes', '2041-03');
        $estadias = collect($cal->json('estadias'));
        $this->assertSame($this->hab1->id, $estadias->firstWhere('codigo', $hoy)['habitacion_id']);
        $this->assertSame('2041-03-04', $estadias->firstWhere('codigo', $manana)['salida']);

        $this->como($this->recepcion())->getJson('/api/admin/calendario?mes=marzo')->assertStatus(422);
    }

    private function servicio(): Servicio
    {
        return Servicio::create([
            'categoria_servicio_id' => \App\Models\CategoriaServicio::value('id'),
            'nombre' => 'Lavandería ' . uniqid(),
            'precio' => 8,
            'forma_cobro' => Servicio::FIJO,
            'estado' => 1,
        ]);
    }
}
