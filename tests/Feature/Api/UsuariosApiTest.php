<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

class UsuariosApiTest extends ApiTestCase
{
    private function datos(array $extra = []): array
    {
        return array_merge([
            'name' => 'Nueva Recepción',
            'email' => 'nueva.recepcion@test.com',
            'password' => 'clave-segura-1',
            'password_confirmation' => 'clave-segura-1',
            'roles' => [Role::where('name', 'Recepcionista')->value('id')],
        ], $extra);
    }

    public function test_exige_autenticacion_y_permiso()
    {
        $this->getJson('/api/admin/usuarios')->assertUnauthorized();
        $this->como($this->huesped())->getJson('/api/admin/usuarios')->assertForbidden();
        $this->como($this->recepcion())->postJson('/api/admin/usuarios', $this->datos())->assertForbidden();
        $this->assertFalse(User::where('email', 'nueva.recepcion@test.com')->exists());
    }

    public function test_listado_con_busqueda_y_conteo_de_sesiones()
    {
        $recepcion = $this->recepcion();
        $this->tokenDe($recepcion);

        $this->como($this->admin())->getJson('/api/admin/usuarios?search=recepcion@')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', $recepcion->email)
            ->assertJsonPath('data.0.sesiones_activas', 1)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
    }

    public function test_catalogo_de_roles()
    {
        $this->como($this->admin())->getJson('/api/admin/usuarios/roles')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_crear_usuario_con_rol_e_idioma()
    {
        $res = $this->como($this->admin())->postJson('/api/admin/usuarios', $this->datos(['idioma' => 'en']));

        $res->assertCreated()
            ->assertJsonPath('data.idioma', 'en')
            ->assertJsonPath('data.estado', 1)
            ->assertJsonPath('data.roles.0', 'Recepcionista')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'nueva.recepcion@test.com')->firstOrFail();
        $this->assertTrue(Hash::check('clave-segura-1', $user->password));
    }

    public function test_el_idioma_por_defecto_es_espanol()
    {
        $this->como($this->admin())->postJson('/api/admin/usuarios', $this->datos())
            ->assertCreated()
            ->assertJsonPath('data.idioma', 'es');
    }

    public function test_validacion_al_crear()
    {
        $admin = $this->como($this->admin());

        $admin->postJson('/api/admin/usuarios', $this->datos(['email' => 'admin@gmail.com']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $admin->postJson('/api/admin/usuarios', $this->datos(['password_confirmation' => 'otra']))
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $admin->postJson('/api/admin/usuarios', $this->datos(['roles' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('roles');
        $admin->postJson('/api/admin/usuarios', $this->datos(['idioma' => 'fr']))
            ->assertStatus(422)->assertJsonValidationErrors('idioma');
    }

    public function test_ver_usuario()
    {
        $admin = $this->como($this->admin());
        $huesped = $this->huesped();

        $admin->getJson("/api/admin/usuarios/{$huesped->id}")->assertOk()->assertJsonPath('data.email', $huesped->email);
        $admin->getJson('/api/admin/usuarios/999999')->assertNotFound();
    }

    public function test_actualizar_sin_contrasena_conserva_las_sesiones()
    {
        $recepcion = $this->recepcion();
        $sesion = $this->tokenDe($recepcion);
        $claveAnterior = $recepcion->password;

        $this->como($this->admin())->putJson("/api/admin/usuarios/{$recepcion->id}", [
            'name' => 'Nombre Editado',
            'email' => $recepcion->email,
            'roles' => [Role::where('name', 'Huésped')->value('id')],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nombre Editado')
            ->assertJsonPath('data.roles.0', 'Huésped');

        $this->assertSame($claveAnterior, $recepcion->fresh()->password);
        $this->conToken($sesion)->getJson('/api/me')->assertOk();
    }

    public function test_cambiar_contrasena_cierra_las_sesiones_del_usuario()
    {
        $recepcion = $this->recepcion();
        $sesion = $this->tokenDe($recepcion);
        $tokenAdmin = $this->tokenDe($this->admin());

        $this->conToken($tokenAdmin)->putJson("/api/admin/usuarios/{$recepcion->id}", [
            'name' => $recepcion->name,
            'email' => $recepcion->email,
            'password' => 'clave-segura-2',
            'password_confirmation' => 'clave-segura-2',
            'roles' => $recepcion->roles->pluck('id')->all(),
        ])->assertOk();

        $this->conToken($sesion)->getJson('/api/me')->assertUnauthorized();
        $this->conToken($tokenAdmin)->getJson('/api/me')->assertOk();
    }

    public function test_cambiar_la_propia_contrasena_conserva_la_sesion_actual()
    {
        $admin = $this->admin();
        $otraSesion = $this->tokenDe($admin);
        $actual = $this->tokenDe($admin);

        $this->conToken($actual)->putJson("/api/admin/usuarios/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => 'clave-segura-2',
            'password_confirmation' => 'clave-segura-2',
            'roles' => $admin->roles->pluck('id')->all(),
        ])->assertOk();

        $this->conToken($actual)->getJson('/api/me')->assertOk();
        $this->conToken($otraSesion)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_desactivar_revoca_tokens_y_bloquea_el_acceso()
    {
        $recepcion = $this->recepcion();
        $sesion = $this->tokenDe($recepcion);
        $tokenAdmin = $this->tokenDe($this->admin());

        $this->conToken($tokenAdmin)->putJson("/api/admin/usuarios/{$recepcion->id}/estado")
            ->assertOk()
            ->assertJsonPath('data.estado', 0);

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $recepcion->id)->count());
        $this->conToken($sesion)->getJson('/api/me')->assertUnauthorized();

        $this->conToken($tokenAdmin)->putJson("/api/admin/usuarios/{$recepcion->id}/estado")
            ->assertOk()
            ->assertJsonPath('data.estado', 1);
    }

    public function test_un_token_deja_de_servir_si_el_usuario_queda_inactivo_por_otra_via()
    {
        $recepcion = $this->recepcion();
        $sesion = $this->tokenDe($recepcion);

        // Por ejemplo, desde el panel Blade, que no revoca tokens.
        $recepcion->update(['estado' => 0]);

        $this->conToken($sesion)->getJson('/api/me')->assertUnauthorized();
        $this->conToken($sesion)->getJson('/api/admin/tipos-habitacion')->assertUnauthorized();
    }

    public function test_nadie_se_desactiva_a_si_mismo()
    {
        $admin = $this->admin();

        $this->como($admin)->putJson("/api/admin/usuarios/{$admin->id}/estado")->assertStatus(422);
        $this->assertSame(1, (int) $admin->fresh()->estado);
    }

    public function test_aviso_de_inicio_de_sesion()
    {
        $huesped = $this->huesped();
        $inicial = (int) $huesped->login_notificacion;

        $this->como($this->admin())->putJson("/api/admin/usuarios/{$huesped->id}/login-notificacion")
            ->assertOk()
            ->assertJsonPath('data.login_notificacion', $inicial === 1 ? 0 : 1);
    }
}
