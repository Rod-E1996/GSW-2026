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
use App\Models\TipoHabitacion;
use App\Models\User;
use App\Pagos\Pasarela;
use App\Services\ComprobanteService;
use App\Services\NotificacionService;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Fase 5: anticipo en la pasarela de pruebas, comprobante PDF con QR,
 * verificacion en recepcion y correos registrados en notificaciones.
 */
class PagoAnticipoApiTest extends ApiTestCase
{
    const APROBADA = '4242 4242 4242 4242';
    const RECHAZADA = '4000 0000 0000 0002';

    private Habitacion $habitacion;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        Configuracion::where('clave', 'anticipo_porcentaje')->update(['valor' => '30']);
        Configuracion::where('clave', 'reserva_pendiente_horas')->update(['valor' => '24']);

        $tipo = TipoHabitacion::create(['nombre' => 'Pago ' . uniqid(), 'capacidad' => 2, 'precio_base' => 100]);
        $this->habitacion = Habitacion::create(['numero' => 'P' . rand(100, 999), 'piso' => 1, 'tipo_habitacion_id' => $tipo->id]);
    }

    private function cuenta(string $nombre = 'Ana'): User
    {
        $user = User::create(['name' => $nombre, 'email' => uniqid() . '@test.com', 'password' => Hash::make('x')]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('Huésped');
        Huesped::paraUsuario($user, ['nombres' => $nombre, 'apellidos' => 'Prueba', 'documento' => '01234567-8', 'tipo_documento' => 'DUI']);

        return $user;
    }

    private function reservaPendiente(User $user, string $llegada = '2041-03-01'): Reserva
    {
        return app(ReservaService::class)->crear([
            'habitacion_id' => $this->habitacion->id,
            'huesped_id' => Huesped::paraUsuario($user)->id,
            'fecha_llegada' => $llegada,
            'fecha_salida' => Carbon::parse($llegada)->addDays(2)->toDateString(),
            'adultos' => 2,
            'canal' => Reserva::CANAL_WEB,
            'user_id' => $user->id,
        ]);
    }

    private function iniciarPago(User $user, Reserva $reserva): string
    {
        return $this->como($user)
            ->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/pagar")
            ->assertOk()
            ->json('data.referencia');
    }

    private function pagarEnLaPasarela(string $referencia, string $tarjeta = self::APROBADA)
    {
        return $this->postJson("/api/pasarela-pruebas/$referencia", [
            'numero' => $tarjeta,
            'titular' => 'ANA PRUEBA',
            'vencimiento' => '12/' . now()->addYears(2)->format('y'),
            'cvv' => '123',
        ]);
    }

    private function notificacionFirmada(array $datos): array
    {
        $cuerpo = json_encode($datos);

        return [$cuerpo, app(Pasarela::class)->firmar($cuerpo)];
    }

    public function test_pagar_exige_sesion_correo_verificado_y_reserva_propia()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);

        $this->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/pagar")->assertUnauthorized();

        $this->como($this->cuenta('Beto'))->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/pagar")->assertNotFound();

        $ana->forceFill(['email_verified_at' => null])->save();
        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/pagar")
            ->assertForbidden()
            ->assertJsonPath('codigo', 'correo_sin_verificar');

        $this->assertSame(0, $reserva->pagos()->count());
    }

    public function test_iniciar_abre_un_cobro_del_anticipo_y_un_doble_clic_no_crea_otro()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);

        $res = $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/pagar")->assertOk();
        $referencia = $res->json('data.referencia');

        $this->assertSame('http://localhost:5173/pasarela-pruebas/' . $referencia, $res->json('data.url'));
        $this->assertEquals(67.80, $res->json('data.monto')); //30 % de 226 (2 noches de 100 + 13 %)

        $this->assertSame($referencia, $this->iniciarPago($ana, $reserva));
        $this->assertSame(1, $reserva->pagos()->count());

        $this->getJson("/api/pasarela-pruebas/$referencia")
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.concepto', 'Anticipo de la reserva ' . $reserva->codigo);
    }

    public function test_pago_aprobado_confirma_emite_el_comprobante_y_envia_el_correo_con_el_pdf()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $referencia = $this->iniciarPago($ana, $reserva);

        $this->pagarEnLaPasarela($referencia)
            ->assertOk()
            ->assertJsonPath('data.estado', 'aprobado')
            ->assertJsonPath('data.url_retorno', "http://localhost:5173/mi-cuenta/reservas/{$reserva->codigo}?pago=$referencia");

        $reserva->refresh();
        $this->assertSame(Reserva::CONFIRMADA, (int) $reserva->estado_reserva);

        $pago = $reserva->pagos()->first();
        $this->assertSame(Pago::APROBADO, (int) $pago->estado_pago);
        $this->assertNotNull($pago->pagado_at);
        $this->assertSame('4242', $pago->respuesta_pasarela['ultimos4']);
        $this->assertArrayNotHasKey('numero', $pago->respuesta_pasarela);
        $this->assertStringNotContainsString('4242424242424242', json_encode($pago->respuesta_pasarela));

        $this->assertStringContainsString('Anticipo de $67.80 aprobado', $reserva->historial()->reorder('id', 'desc')->first()->comentario);

        $comprobante = $reserva->comprobanteVigente()->first();
        $this->assertSame('CMP-' . substr($reserva->codigo, 3) . '-1', $comprobante->numero);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($comprobante->qr_token));
        Storage::disk('local')->assertExists($comprobante->ruta_pdf);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($comprobante->ruta_pdf));

        Mail::assertSent(ReservaConfirmadaMail::class, function ($correo) use ($ana) {
            $correo->build();

            return $correo->hasTo(Huesped::paraUsuario($ana)->email)
                && count($correo->rawAttachments) === 1
                && str_starts_with($correo->rawAttachments[0]['data'], '%PDF');
        });

        $aviso = Notificacion::where('reserva_id', $reserva->id)->where('tipo', Notificacion::CONFIRMACION)->sole();
        $this->assertSame(Notificacion::ENVIADO, (int) $aviso->estado_envio);
        $this->assertSame(1, (int) $aviso->intentos);
        $this->assertNotNull($aviso->enviado_at);
    }

    public function test_pago_rechazado_no_confirma_y_se_puede_reintentar()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $referencia = $this->iniciarPago($ana, $reserva);

        $this->pagarEnLaPasarela($referencia, self::RECHAZADA)
            ->assertOk()
            ->assertJsonPath('data.estado', 'rechazado')
            ->assertJsonPath('data.motivo', 'Tarjeta rechazada por el banco emisor.');

        $this->assertSame(Reserva::PENDIENTE, (int) $reserva->fresh()->estado_reserva);
        $this->assertSame(Pago::RECHAZADO, (int) Pago::where('referencia_externa', $referencia)->value('estado_pago'));
        $this->assertNull($reserva->comprobanteVigente()->first());
        Mail::assertNotSent(ReservaConfirmadaMail::class);

        //El cobro rechazado no se puede volver a usar; se abre otro
        $this->pagarEnLaPasarela($referencia)->assertStatus(409);
        $nueva = $this->iniciarPago($ana, $reserva);
        $this->assertNotSame($referencia, $nueva);

        $this->pagarEnLaPasarela($nueva)->assertJsonPath('data.estado', 'aprobado');
        $this->assertSame(Reserva::CONFIRMADA, (int) $reserva->fresh()->estado_reserva);
    }

    public function test_la_misma_notificacion_dos_veces_no_duplica_nada()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $referencia = $this->iniciarPago($ana, $reserva);

        [$cuerpo, $firma] = $this->notificacionFirmada([
            'referencia' => $referencia, 'estado' => 'aprobado', 'monto' => '67.80', 'autorizacion' => 'ABC123',
        ]);

        $enviar = fn () => $this->call('POST', '/api/pagos/notificacion', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_FIRMA' => $firma,
        ], $cuerpo);

        $enviar()->assertOk()->assertJsonPath('repetida', false);
        $enviar()->assertOk()->assertJsonPath('repetida', true);

        $this->assertSame(1, $reserva->pagos()->aprobados()->count());
        $this->assertSame(1, $reserva->comprobantes()->count());
        $this->assertSame(1, $reserva->historial()->where('estado_nuevo', Reserva::CONFIRMADA)->count());
        Mail::assertSent(ReservaConfirmadaMail::class, 1);
    }

    public function test_una_notificacion_sin_firma_valida_no_toca_nada()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $referencia = $this->iniciarPago($ana, $reserva);
        $cuerpo = json_encode(['referencia' => $referencia, 'estado' => 'aprobado', 'monto' => '67.80']);

        $this->call('POST', '/api/pagos/notificacion', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_FIRMA' => hash_hmac('sha256', $cuerpo, 'otra-clave'),
        ], $cuerpo)->assertStatus(400);

        $this->call('POST', '/api/pagos/notificacion', [], [], [], ['CONTENT_TYPE' => 'application/json'], $cuerpo)
            ->assertStatus(400);

        $this->assertSame(Reserva::PENDIENTE, (int) $reserva->fresh()->estado_reserva);
        $this->assertSame(Pago::PENDIENTE, (int) Pago::where('referencia_externa', $referencia)->value('estado_pago'));
    }

    public function test_un_cobro_por_un_monto_distinto_se_rechaza()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $referencia = $this->iniciarPago($ana, $reserva);
        [$cuerpo, $firma] = $this->notificacionFirmada(['referencia' => $referencia, 'estado' => 'aprobado', 'monto' => '1.00']);

        $this->call('POST', '/api/pagos/notificacion', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FIRMA' => $firma], $cuerpo)
            ->assertOk();

        $this->assertSame(Pago::RECHAZADO, (int) Pago::where('referencia_externa', $referencia)->value('estado_pago'));
        $this->assertSame(Reserva::PENDIENTE, (int) $reserva->fresh()->estado_reserva);
    }

    public function test_la_pasarela_valida_la_tarjeta_antes_de_cobrar()
    {
        $ana = $this->cuenta();
        $referencia = $this->iniciarPago($ana, $this->reservaPendiente($ana));

        $this->postJson("/api/pasarela-pruebas/$referencia", [
            'numero' => '4242 4242 4242 4241',
            'titular' => 'ANA',
            'vencimiento' => '01/20',
            'cvv' => '12',
        ])->assertStatus(422)->assertJsonValidationErrors(['numero', 'vencimiento', 'cvv']);

        $this->getJson('/api/pasarela-pruebas/no-existe')->assertNotFound();
        $this->assertSame(Pago::PENDIENTE, (int) Pago::where('referencia_externa', $referencia)->value('estado_pago'));
    }

    public function test_no_se_paga_una_reserva_confirmada_ni_cancelada()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $this->pagarEnLaPasarela($this->iniciarPago($ana, $reserva));

        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/pagar")
            ->assertStatus(422)
            ->assertJsonPath('errors.reserva.0', 'La reserva ya está confirmada: su anticipo ya se pagó.');

        $otra = $this->reservaPendiente($ana, '2041-05-01');
        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$otra->codigo}/cancelar")->assertOk();
        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$otra->codigo}/pagar")->assertStatus(422);
    }

    public function test_una_pendiente_vencida_se_paga_solo_si_la_habitacion_sigue_libre()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $reserva->forceFill(['created_at' => now()->subHours(25)])->save();

        //Nadie tomo la habitacion: todavia se puede pagar y confirmar
        $this->pagarEnLaPasarela($this->iniciarPago($ana, $reserva))->assertJsonPath('data.estado', 'aprobado');
        $this->assertSame(Reserva::CONFIRMADA, (int) $reserva->fresh()->estado_reserva);

        //Otra vencida cuya habitacion ya reservo alguien mas
        $vencida = $this->reservaPendiente($ana, '2041-06-01');
        $vencida->forceFill(['created_at' => now()->subHours(25)])->save();
        $this->reservaPendiente($this->cuenta('Beto'), '2041-06-01');

        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$vencida->codigo}/pagar")
            ->assertStatus(422)
            ->assertJsonPath('errors.reserva.0', 'La habitación ya no está disponible para esas fechas. Haz una reserva nueva.');
    }

    public function test_un_pago_aprobado_de_una_reserva_ya_cancelada_queda_para_reembolso()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $referencia = $this->iniciarPago($ana, $reserva);

        //El huesped cancela mientras la pasarela procesa el cobro
        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/cancelar")->assertOk();
        $this->pagarEnLaPasarela($referencia)->assertJsonPath('data.estado', 'aprobado');

        $this->assertSame(Reserva::CANCELADA, (int) $reserva->fresh()->estado_reserva);
        $this->assertNull($reserva->comprobanteVigente()->first());
        $this->assertStringContainsString('el hotel debe reembolsarlo', $reserva->historial()->reorder('id', 'desc')->first()->comentario);

        $this->como($ana)->getJson("/api/mi-cuenta/reservas/{$reserva->codigo}")
            ->assertJsonPath('data.pagos.0.requiere_reembolso', true);
    }

    public function test_cancelar_despues_de_pagar_reembolsa_lo_pagado_y_anula_el_comprobante()
    {
        Configuracion::where('clave', 'cancelacion_reembolso_porcentaje')->update(['valor' => '100']);
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $this->pagarEnLaPasarela($this->iniciarPago($ana, $reserva));
        $token = $reserva->comprobanteVigente()->value('qr_token');

        $this->como($ana)->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.pagos.0.requiere_reembolso', false);

        $this->assertStringContainsString('100 % del anticipo pagado ($67.80)', $reserva->historial()->reorder('id', 'desc')->first()->comentario);
        $this->assertSame(Comprobante::ANULADO, (int) Comprobante::where('qr_token', $token)->value('estado'));

        $this->como($this->recepcion())->postJson("/api/admin/comprobantes/$token/verificar")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Comprobante anulado: la reserva fue cancelada.');
    }

    public function test_el_huesped_ve_y_descarga_su_comprobante_y_nadie_mas()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $this->pagarEnLaPasarela($this->iniciarPago($ana, $reserva));

        $detalle = $this->como($ana)->getJson("/api/mi-cuenta/reservas/{$reserva->codigo}")->assertOk();
        $detalle->assertJsonPath('data.pagado', 67.8)
            ->assertJsonPath('data.saldo', 158.2)
            ->assertJsonPath('data.puede_pagar', false)
            ->assertJsonPath('data.pagos.0.medio', 'Visa terminada en 4242');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $detalle->json('data.comprobante.qr'));

        $pdf = $this->como($ana)->get("/api/mi-cuenta/reservas/{$reserva->codigo}/comprobante")->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->como($this->cuenta('Beto'))->get("/api/mi-cuenta/reservas/{$reserva->codigo}/comprobante")->assertNotFound();
    }

    public function test_si_el_pdf_se_pierde_se_vuelve_a_generar_al_descargarlo()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $this->pagarEnLaPasarela($this->iniciarPago($ana, $reserva));
        Storage::disk('local')->delete($reserva->comprobanteVigente()->value('ruta_pdf'));

        $pdf = $this->como($ana)->get("/api/mi-cuenta/reservas/{$reserva->codigo}/comprobante")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_recepcion_verifica_el_comprobante_por_qr_o_por_codigo()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $this->pagarEnLaPasarela($this->iniciarPago($ana, $reserva));
        $token = $reserva->comprobanteVigente()->value('qr_token');
        $recepcion = $this->recepcion();

        $this->como($recepcion)->getJson("/api/admin/comprobantes/$token")
            ->assertOk()
            ->assertJsonPath('data.valido', true)
            ->assertJsonPath('data.reserva.codigo', $reserva->codigo)
            ->assertJsonPath('data.reserva.titular', 'Ana Prueba')
            ->assertJsonPath('data.reserva.saldo', 158.2)
            ->assertJsonPath('data.comprobante.verificado_at', null);

        $this->como($recepcion)->getJson("/api/admin/comprobantes/{$reserva->codigo}")
            ->assertOk()
            ->assertJsonPath('data.comprobante.qr_token', $token);

        $this->como($recepcion)->postJson("/api/admin/comprobantes/$token/verificar")
            ->assertOk()
            ->assertJsonPath('data.comprobante.verificado_por', $recepcion->name);

        //La primera verificacion es la que queda
        $primera = Comprobante::where('qr_token', $token)->value('verificado_at');
        $this->travel(1)->hours();
        $this->como($this->admin())->postJson("/api/admin/comprobantes/$token/verificar")->assertOk();
        $this->assertEquals($primera, Comprobante::where('qr_token', $token)->value('verificado_at'));

        $pdf = $this->como($recepcion)->get("/api/admin/comprobantes/$token/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->como($recepcion)->getJson('/api/admin/comprobantes/00000000-0000-4000-8000-000000000000')->assertNotFound();
    }

    public function test_el_huesped_no_puede_usar_la_verificacion_de_recepcion()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $this->pagarEnLaPasarela($this->iniciarPago($ana, $reserva));
        $token = $reserva->comprobanteVigente()->value('qr_token');

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson("/api/admin/comprobantes/$token")->assertUnauthorized();
        $this->como($ana)->getJson("/api/admin/comprobantes/$token")->assertForbidden();
    }

    public function test_una_version_nueva_sustituye_a_la_anterior()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $this->pagarEnLaPasarela($this->iniciarPago($ana, $reserva));
        $primera = $reserva->comprobanteVigente()->first();

        $segunda = app(ComprobanteService::class)->reemitir($reserva);

        $this->assertSame(2, (int) $segunda->version);
        $this->assertSame($segunda->id, $reserva->comprobanteVigente()->first()->id);
        $this->assertSame(Comprobante::SUSTITUIDO, (int) $primera->fresh()->estado);
        $this->assertNotSame($primera->qr_token, $segunda->qr_token);

        $this->como($this->recepcion())->getJson("/api/admin/comprobantes/{$primera->qr_token}")
            ->assertJsonPath('data.valido', false);
        $this->como($this->recepcion())->getJson("/api/admin/comprobantes/{$reserva->codigo}")
            ->assertJsonPath('data.comprobante.version', 2);
    }

    public function test_registrar_la_reserva_avisa_el_plazo_para_pagar()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);

        app(NotificacionService::class)->registro($reserva);

        Mail::assertSent(ReservaRegistradaMail::class, fn ($correo) => str_contains($correo->render(), 'Pagar el anticipo'));
        $this->assertSame(
            'Reserva ' . $reserva->codigo . ' registrada: falta el anticipo',
            Notificacion::where('reserva_id', $reserva->id)->sole()->asunto
        );
    }

    public function test_el_correo_de_modificacion_lista_los_cambios()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);

        app(NotificacionService::class)->modificacion($reserva, [
            ['campo' => 'Llegada', 'antes' => '01/03/2041', 'despues' => '02/03/2041'],
        ]);

        Mail::assertSent(ReservaModificadaMail::class, fn ($correo) => str_contains($correo->render(), '02/03/2041'));
        $this->assertSame(Notificacion::MODIFICACION, Notificacion::where('reserva_id', $reserva->id)->sole()->tipo);
    }

    public function test_un_correo_que_falla_queda_registrado_y_no_deshace_el_pago()
    {
        $ana = $this->cuenta();
        $reserva = $this->reservaPendiente($ana);
        $referencia = $this->iniciarPago($ana, $reserva);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->pagarEnLaPasarela($referencia)->assertOk()->assertJsonPath('data.estado', 'aprobado');

        $this->assertSame(Reserva::CONFIRMADA, (int) $reserva->fresh()->estado_reserva);
        $aviso = Notificacion::where('reserva_id', $reserva->id)->where('tipo', Notificacion::CONFIRMACION)->sole();
        $this->assertSame(Notificacion::FALLIDO, (int) $aviso->estado_envio);
        $this->assertStringContainsString('SMTP caído', $aviso->error);
        $this->assertSame(1, (int) $aviso->intentos);
    }

    public function test_crear_la_reserva_en_linea_envia_el_aviso_de_registro()
    {
        $huesped = $this->huesped();
        $llegada = '2041-08-01';

        $this->como($huesped)->postJson('/api/reservas', [
            'habitacion_id' => $this->habitacion->id,
            'fecha_llegada' => $llegada,
            'fecha_salida' => '2041-08-03',
            'adultos' => 1,
            'nombres' => 'Huésped',
            'apellidos' => 'Demo',
            'tipo_documento' => 'DUI',
            'documento' => '01234567-8',
            'telefono' => '70000000',
        ])->assertCreated();

        Mail::assertSent(ReservaRegistradaMail::class, 1);
        $this->assertSame(1, Notificacion::where('tipo', Notificacion::REGISTRO)->where('user_id', $huesped->id)->count());
    }
}
