<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermisosApiTest extends ApiTestCase
{
    public function test_roles_exigen_autenticacion_y_permiso()
    {
        $this->getJson('/api/admin/roles')->assertUnauthorized();
        $this->como($this->recepcion())->getJson('/api/admin/roles')->assertForbidden();
        $this->como($this->huesped())->postJson('/api/admin/roles', ['name' => 'Intruso'])->assertForbidden();
        $this->assertFalse(Role::where('name', 'Intruso')->exists());
    }

    public function test_listar_roles_con_busqueda_y_conteo()
    {
        $this->como($this->admin())->getJson('/api/admin/roles?search=Recep')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Recepcionista')
            ->assertJsonStructure(['data' => [['id', 'name', 'permisos_count']], 'meta']);
    }

    public function test_crear_editar_y_eliminar_rol()
    {
        $admin = $this->como($this->admin());

        $id = $admin->postJson('/api/admin/roles', ['name' => 'Gerencia'])->assertCreated()->json('data.id');
        $this->assertSame('web', Role::find($id)->guard_name);

        $admin->postJson('/api/admin/roles', ['name' => 'Gerencia'])->assertStatus(422)->assertJsonValidationErrors('name');

        $admin->putJson("/api/admin/roles/$id", ['name' => 'Gerencia General'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Gerencia General');

        $admin->deleteJson("/api/admin/roles/$id")->assertOk();
        $this->assertNull(Role::find($id));
        $admin->getJson("/api/admin/roles/$id")->assertNotFound();
    }

    public function test_no_se_elimina_un_rol_con_usuarios()
    {
        $rol = Role::where('name', 'Recepcionista')->firstOrFail();

        $this->como($this->admin())->deleteJson("/api/admin/roles/{$rol->id}")->assertStatus(422);
        $this->assertNotNull(Role::find($rol->id));
    }

    public function test_ver_rol_separa_permisos_asignados_y_disponibles()
    {
        $rol = Role::where('name', 'Huésped')->firstOrFail();

        $res = $this->como($this->admin())->getJson("/api/admin/roles/{$rol->id}")->assertOk();

        $this->assertContains('perfil_show', $res->json('data.permisos_asignados'));
        $this->assertContains('usuario_index', $res->json('data.permisos_disponibles'));
        $this->assertNotContains('perfil_show', $res->json('data.permisos_disponibles'));
    }

    public function test_asignar_permisos_cambia_el_acceso_real_del_rol()
    {
        $recepcion = $this->recepcion();
        $rol = Role::where('name', 'Recepcionista')->firstOrFail();
        $tokenAdmin = $this->tokenDe($this->admin());
        $tokenRecepcion = $this->tokenDe($recepcion);

        $this->conToken($tokenRecepcion)->getJson('/api/admin/auditoria')->assertForbidden();

        $permisos = $rol->permissions->pluck('name')->push('auditar_index')->all();
        $this->conToken($tokenAdmin)->putJson("/api/admin/roles/{$rol->id}/permisos", ['permisos' => $permisos])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->conToken($tokenRecepcion)->getJson('/api/admin/auditoria')->assertOk();
    }

    public function test_asignar_permisos_rechaza_un_permiso_inexistente()
    {
        $rol = Role::where('name', 'Huésped')->firstOrFail();

        $this->como($this->admin())->putJson("/api/admin/roles/{$rol->id}/permisos", ['permisos' => ['no_existe']])
            ->assertStatus(422);
    }

    public function test_el_super_administrador_conserva_sus_permisos_clave()
    {
        $rol = Role::where('name', 'Super Administrador')->firstOrFail();

        $res = $this->como($this->admin())->putJson("/api/admin/roles/{$rol->id}/permisos", ['permisos' => []]);

        $res->assertOk()->assertJsonPath('success', false);
        $this->assertContains('role_move_permiso', $res->json('permisos_asignados'));
        $this->assertContains('usuario_index', $res->json('permisos_asignados'));
    }

    public function test_permisos_exigen_autenticacion_y_permiso()
    {
        $this->getJson('/api/admin/permisos')->assertUnauthorized();
        $this->como($this->recepcion())->getJson('/api/admin/permisos')->assertForbidden();
        $this->como($this->huesped())->postJson('/api/admin/permisos', ['name' => 'intruso'])->assertForbidden();
    }

    public function test_listar_permisos_con_busqueda()
    {
        $this->como($this->admin())->getJson('/api/admin/permisos?search=usuario_index')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'usuario_index');
    }

    public function test_crear_y_eliminar_permiso()
    {
        $admin = $this->como($this->admin());

        $id = $admin->postJson('/api/admin/permisos', ['name' => 'reporte_prueba'])->assertCreated()->json('data.id');
        $this->assertSame('web', Permission::find($id)->guard_name);

        $admin->postJson('/api/admin/permisos', ['name' => 'reporte_prueba'])->assertStatus(422);

        $admin->deleteJson("/api/admin/permisos/$id")->assertOk();
        $this->assertNull(Permission::find($id));
        $admin->deleteJson("/api/admin/permisos/$id")->assertNotFound();
    }

    public function test_no_se_elimina_un_permiso_asignado()
    {
        $permiso = Permission::where('name', 'usuario_index')->firstOrFail();

        $this->como($this->admin())->deleteJson("/api/admin/permisos/{$permiso->id}")->assertStatus(422);
        $this->assertNotNull(Permission::find($permiso->id));
    }
}
