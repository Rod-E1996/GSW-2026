<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifica los roles del hotel y el acceso de cada uno.
 * Requiere la base de datos sembrada con RolesHotelSeeder.
 */
class RolesHotelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_existen_los_tres_roles_del_hotel()
    {
        $roles = Role::pluck('name')->toArray();

        $this->assertEqualsCanonicalizing(
            ['Super Administrador', 'Recepcionista', 'Huésped'],
            $roles
        );
    }

    public function test_recepcionista_entra_al_dashboard_y_consulta_tipos_pero_no_edita()
    {
        $recepcion = User::where('email', 'recepcion@hotellink.com')->firstOrFail();

        $this->actingAs($recepcion)->get('/afterlogin')->assertRedirect('/dashboard');
        $this->actingAs($recepcion)->get('/dashboard')->assertOk();
        $this->actingAs($recepcion)->get('/tipo_habitacion')->assertOk();
        $this->actingAs($recepcion)->get('/tipo_habitacion/create')->assertForbidden();
        $this->actingAs($recepcion)->get('/role')->assertForbidden();
    }

    public function test_huesped_no_entra_a_la_administracion()
    {
        $huesped = User::where('email', 'huesped@hotellink.com')->firstOrFail();

        $this->actingAs($huesped)->get('/afterlogin')->assertRedirect('/');
        $this->actingAs($huesped)->get('/dashboard')->assertForbidden();
        $this->actingAs($huesped)->get('/tipo_habitacion')->assertForbidden();
        //La vista de perfil requiere una sesión real en la tabla sessions (no existe en tests),
        //así que se verifica el permiso directamente
        $this->assertTrue($huesped->can('perfil_show'));
        $this->assertFalse($huesped->can('tipo_habitacion_index'));
    }

    public function test_super_administrador_gestiona_tipos_de_habitacion_y_roles()
    {
        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();

        $this->actingAs($admin)->get('/afterlogin')->assertRedirect('/dashboard');
        $this->actingAs($admin)->get('/tipo_habitacion/create')->assertOk();
        $this->actingAs($admin)->get('/role')->assertOk();
    }

    public function test_registro_publico_crea_un_huesped()
    {
        $this->post('/register', [
            'name' => 'Nuevo Huésped',
            'email' => 'nuevo.huesped@test.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);

        $user = User::where('email', 'nuevo.huesped@test.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Huésped'));
    }
}
