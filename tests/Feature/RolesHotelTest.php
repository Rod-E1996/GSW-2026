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

    public function test_registro_publico_por_api_crea_un_huesped_con_token()
    {
        $res = $this->postJson('/api/register', [
            'nombres' => 'API',
            'apellidos' => 'Huesped',
            'email' => 'api.huesped@test.com',
            'telefono' => '70003333',
            'password' => '12345678',
            'password_confirmation' => '12345678',
            'consentimiento' => true,
        ]);

        $res->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'acceso_panel']])
            ->assertJsonPath('user.acceso_panel', false);

        $user = User::where('email', 'api.huesped@test.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Huésped'));
    }

    public function test_login_por_api_expone_acceso_panel_segun_rol()
    {
        // Staff (Super Administrador) -> acceso al panel
        $this->postJson('/api/login', ['email' => 'admin@gmail.com', 'password' => \Database\Seeders\ContrasenasDemo::ADMIN])
            ->assertOk()
            ->assertJsonPath('user.acceso_panel', true);

        // Externo (Huésped) -> sin acceso al panel
        $this->postJson('/api/login', ['email' => 'huesped@hotellink.com', 'password' => \Database\Seeders\ContrasenasDemo::HUESPED])
            ->assertOk()
            ->assertJsonPath('user.acceso_panel', false);
    }
}
