<?php

namespace Tests\Feature\Api;

use App\Models\SessionLog;
use Laravel\Sanctum\PersonalAccessToken;

class SesionesApiTest extends ApiTestCase
{
    private function idDe(string $token): int
    {
        return (int) explode('|', $token)[0];
    }

    public function test_exigen_autenticacion_y_permiso()
    {
        $huesped = $this->huesped();

        $this->getJson('/api/admin/sesiones')->assertUnauthorized();
        $this->como($this->recepcion())->getJson('/api/admin/sesiones')->assertForbidden();
        $this->como($this->recepcion())->getJson("/api/admin/usuarios/{$huesped->id}/sesiones")->assertForbidden();
    }

    public function test_vista_global_marca_la_sesion_actual_y_muestra_el_dispositivo()
    {
        $admin = $this->admin();
        $login = $this->postJson('/api/login', ['email' => $admin->email, 'password' => '12345678'])->json('token');

        $res = $this->conToken($login)->getJson('/api/admin/sesiones')->assertOk();

        $res->assertJsonStructure([
            'data' => [['id', 'usuario', 'ip_address', 'platform', 'browser', 'device_type_es', 'inicio', 'es_actual']],
            'meta',
            'stats' => ['total_activas', 'usuarios_conectados'],
        ]);

        $actual = collect($res->json('data'))->firstWhere('id', $this->idDe($login));
        $this->assertTrue($actual['es_actual']);
        $this->assertSame($admin->name, $actual['usuario']);
    }

    public function test_busqueda_global_por_usuario()
    {
        $this->tokenDe($this->huesped());

        $res = $this->como($this->admin())->getJson('/api/admin/sesiones?search=huesped@')->assertOk();

        $this->assertSame(['Huésped de Prueba'], collect($res->json('data'))->pluck('usuario')->unique()->values()->all());
    }

    public function test_cerrar_una_sesion_ajena_la_invalida()
    {
        $sesion = $this->tokenDe($this->huesped());
        $tokenAdmin = $this->tokenDe($this->admin());

        $this->conToken($tokenAdmin)->deleteJson('/api/admin/sesiones/' . $this->idDe($sesion))->assertOk();

        $this->assertNull(PersonalAccessToken::find($this->idDe($sesion)));
        $this->conToken($sesion)->getJson('/api/me')->assertUnauthorized();

        $this->conToken($tokenAdmin)->deleteJson('/api/admin/sesiones/' . $this->idDe($sesion))->assertNotFound();
    }

    public function test_no_se_puede_cerrar_la_sesion_actual()
    {
        $token = $this->tokenDe($this->admin());

        $this->conToken($token)->deleteJson('/api/admin/sesiones/' . $this->idDe($token))->assertStatus(422);
        $this->conToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_sesiones_e_historial_de_un_usuario()
    {
        $huesped = $this->huesped();
        $token = $this->postJson('/api/login', ['email' => $huesped->email, 'password' => '12345678'])->json('token');

        $res = $this->como($this->admin())->getJson("/api/admin/usuarios/{$huesped->id}/sesiones")->assertOk();

        $res->assertJsonPath('usuario.id', $huesped->id)->assertJsonPath('total_activas', 1);
        $this->assertSame($this->idDe($token), $res->json('sesiones.0.id'));
        $this->assertGreaterThanOrEqual(1, count($res->json('historial')));

        $this->getJson('/api/admin/usuarios/999999/sesiones')->assertNotFound();
    }

    public function test_cerrar_sesion_de_un_usuario_valida_que_le_pertenezca()
    {
        $huesped = $this->huesped();
        $recepcion = $this->recepcion();
        $sesionRecepcion = $this->tokenDe($recepcion);
        $sesionHuesped = $this->tokenDe($huesped);
        $tokenAdmin = $this->tokenDe($this->admin());

        $this->conToken($tokenAdmin)
            ->deleteJson("/api/admin/usuarios/{$huesped->id}/sesiones/" . $this->idDe($sesionRecepcion))
            ->assertNotFound();
        $this->conToken($sesionRecepcion)->getJson('/api/me')->assertOk();

        $this->conToken($tokenAdmin)
            ->deleteJson("/api/admin/usuarios/{$huesped->id}/sesiones/" . $this->idDe($sesionHuesped))
            ->assertOk();
        $this->conToken($sesionHuesped)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_cerrar_todas_las_sesiones_de_un_usuario()
    {
        $huesped = $this->huesped();
        $this->tokenDe($huesped);
        $this->tokenDe($huesped);
        $tokenAdmin = $this->tokenDe($this->admin());

        $this->conToken($tokenAdmin)->deleteJson("/api/admin/usuarios/{$huesped->id}/sesiones")
            ->assertOk()
            ->assertJsonPath('cerradas', 2);

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $huesped->id)->count());
        $this->conToken($tokenAdmin)->deleteJson('/api/admin/usuarios/999999/sesiones')->assertNotFound();
    }

    public function test_cerrar_todas_las_propias_conserva_la_actual()
    {
        $admin = $this->admin();
        $otra = $this->tokenDe($admin);
        $actual = $this->tokenDe($admin);

        $this->conToken($actual)->deleteJson("/api/admin/usuarios/{$admin->id}/sesiones")
            ->assertOk()
            ->assertJsonPath('cerradas', 1);

        $this->conToken($actual)->getJson('/api/me')->assertOk();
        $this->conToken($otra)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_cerrar_una_sesion_actualiza_su_registro()
    {
        $huesped = $this->huesped();
        $token = $this->postJson('/api/login', ['email' => $huesped->email, 'password' => '12345678'])->json('token');

        $this->como($this->admin())->deleteJson('/api/admin/sesiones/' . $this->idDe($token))->assertOk();

        $this->assertSame(0, (int) SessionLog::where('session_id', (string) $this->idDe($token))->value('estado'));
    }
}
