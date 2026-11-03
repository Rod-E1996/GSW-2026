<?php

namespace Tests\Feature\Api;

use App\Jobs\SendEmail;
use App\Models\AuditarDetalle;
use App\Models\ErrorLog;
use Illuminate\Support\Facades\Bus;

class AuditoriaErroresApiTest extends ApiTestCase
{
    public function test_auditoria_exige_autenticacion_y_permiso()
    {
        $this->getJson('/api/admin/auditoria')->assertUnauthorized();
        $this->como($this->recepcion())->getJson('/api/admin/auditoria')->assertForbidden();
        $this->como($this->huesped())->getJson('/api/admin/auditoria/1')->assertForbidden();
    }

    public function test_las_operaciones_por_api_registran_quien_las_hizo()
    {
        $admin = $this->admin();
        $huesped = $this->huesped();
        $tokenAdmin = $this->tokenDe($admin);

        $this->conToken($tokenAdmin)->putJson("/api/admin/usuarios/{$huesped->id}/estado")->assertOk();

        $registro = AuditarDetalle::where('primary_key', $huesped->id)->latest('id')->firstOrFail();
        $this->assertSame($admin->id, (int) $registro->user_id);
        $this->assertSame(5, (int) $registro->auditar_accion_id); // desactivar

        $this->conToken($tokenAdmin)->getJson("/api/admin/auditoria/{$registro->id}")
            ->assertOk()
            ->assertJsonPath('data.usuario', $admin->name)
            ->assertJsonPath('data.antes.estado', 1)
            ->assertJsonPath('data.despues.estado', 0);
    }

    public function test_la_bitacora_no_expone_contrasenas()
    {
        $huesped = $this->huesped();

        $tokenAdmin = $this->tokenDe($this->admin());
        $this->conToken($tokenAdmin)->putJson("/api/admin/usuarios/{$huesped->id}/estado")->assertOk();

        $registro = AuditarDetalle::where('primary_key', $huesped->id)->latest('id')->firstOrFail();
        $res = $this->conToken($tokenAdmin)->getJson("/api/admin/auditoria/{$registro->id}")->assertOk();

        $this->assertArrayNotHasKey('password', $res->json('data.despues'));
        $this->assertArrayNotHasKey('remember_token', $res->json('data.despues'));
    }

    public function test_listado_de_auditoria_con_busqueda()
    {
        $admin = $this->admin();
        $huesped = $this->huesped();
        $tokenAdmin = $this->tokenDe($admin);
        $this->conToken($tokenAdmin)->putJson("/api/admin/usuarios/{$huesped->id}/estado")->assertOk();

        $res = $this->conToken($tokenAdmin)->getJson('/api/admin/auditoria?search=' . urlencode($admin->name))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'usuario', 'accion', 'entidad', 'fecha']], 'meta']);

        $this->assertSame([$admin->name], collect($res->json('data'))->pluck('usuario')->unique()->values()->all());
        $this->conToken($tokenAdmin)->getJson('/api/admin/auditoria/999999')->assertNotFound();
    }

    public function test_errores_exigen_autenticacion_y_permiso()
    {
        $this->getJson('/api/admin/error-logs')->assertUnauthorized();
        $this->como($this->recepcion())->getJson('/api/admin/error-logs')->assertForbidden();
        $this->como($this->huesped())->postJson('/api/admin/error-logs/prueba')->assertForbidden();
    }

    public function test_generar_error_de_prueba_lo_guarda_y_avisa_por_correo()
    {
        Bus::fake();
        $admin = $this->admin();
        $token = $this->tokenDe($admin);

        $this->conToken($token)->postJson('/api/admin/error-logs/prueba')->assertOk();

        $error = ErrorLog::latest('id')->firstOrFail();
        $this->assertSame($admin->id, (int) $error->user_id);
        $this->assertStringContainsString('ErrorLogController', $error->controller);
        Bus::assertDispatched(SendEmail::class);
    }

    public function test_listar_ver_y_resolver_un_error()
    {
        $error = ErrorLog::withoutEvents(fn () => ErrorLog::create([
            'controller' => 'ControladorDePrueba@accion',
            'mensaje' => 'Mensaje único de prueba',
            'parametros' => '{"id":"7"}',
        ]));
        $token = $this->tokenDe($this->admin());

        $this->conToken($token)->getJson('/api/admin/error-logs?search=' . urlencode('único de prueba'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 0)
            ->assertJsonPath('data.0.parametros_json.id', '7');

        $this->conToken($token)->getJson("/api/admin/error-logs/{$error->id}")
            ->assertOk()
            ->assertJsonPath('data.mensaje', 'Mensaje único de prueba');

        $this->conToken($token)->putJson("/api/admin/error-logs/{$error->id}/estado")
            ->assertOk()
            ->assertJsonPath('data.estado', 1);

        $this->conToken($token)->getJson('/api/admin/error-logs/999999')->assertNotFound();
        $this->conToken($token)->putJson('/api/admin/error-logs/999999/estado')->assertNotFound();
    }
}
