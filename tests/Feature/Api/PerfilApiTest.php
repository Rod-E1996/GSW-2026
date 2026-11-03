<?php

namespace Tests\Feature\Api;

use App\Models\Huesped;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class PerfilApiTest extends ApiTestCase
{
    private function usuario(string $rol = 'Recepcionista'): User
    {
        $user = User::create([
            'name' => 'Perfil Prueba',
            'email' => 'perfil' . uniqid() . '@prueba.test',
            'password' => Hash::make('clave-actual'),
        ]);
        $user->assignRole($rol);

        return $user;
    }

    private function datos(User $user, array $extra = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'idioma' => 'es',
        ], $extra);
    }

    public function test_exige_autenticacion()
    {
        $this->getJson('/api/perfil')->assertUnauthorized();
        $this->putJson('/api/perfil/password')->assertUnauthorized();
    }

    public function test_muestra_datos_sesiones_e_historial()
    {
        $user = $this->usuario();
        $this->tokenDe($user);

        $res = $this->como($user)->getJson('/api/perfil')->assertOk();

        $res->assertJsonPath('user.email', $user->email)->assertJsonCount(2, 'sesiones');
        $this->assertSame(1, collect($res->json('sesiones'))->where('es_actual', true)->count());
    }

    public function test_el_huesped_tambien_tiene_perfil()
    {
        $this->como($this->huesped())->getJson('/api/perfil')->assertOk();
    }

    public function test_edita_sus_datos_y_limpia_el_telefono()
    {
        $user = $this->usuario();

        $this->como($user)
            ->putJson('/api/perfil', $this->datos($user, ['name' => 'Nombre Nuevo', 'phone' => '(503) 7000-1234', 'idioma' => 'en']))
            ->assertOk()
            ->assertJsonPath('user.name', 'Nombre Nuevo')
            ->assertJsonPath('user.phone', '50370001234')
            ->assertJsonPath('user.idioma', 'en');
    }

    public function test_cambiar_el_correo_pide_la_contrasena()
    {
        $user = $this->usuario();
        $nuevo = 'nuevo' . uniqid() . '@prueba.test';

        $this->como($user)
            ->putJson('/api/perfil', $this->datos($user, ['email' => $nuevo]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->como($user)
            ->putJson('/api/perfil', $this->datos($user, ['email' => $nuevo, 'current_password' => 'incorrecta']))
            ->assertStatus(422);

        $this->assertNotSame($nuevo, $user->fresh()->email);

        $this->como($user)
            ->putJson('/api/perfil', $this->datos($user, ['email' => $nuevo, 'current_password' => 'clave-actual']))
            ->assertOk();

        $this->assertSame($nuevo, $user->fresh()->email);
    }

    public function test_no_acepta_el_correo_de_otra_cuenta_ni_un_idioma_desconocido()
    {
        $user = $this->usuario();

        $this->como($user)
            ->putJson('/api/perfil', $this->datos($user, ['email' => 'admin@gmail.com', 'idioma' => 'fr', 'current_password' => 'clave-actual']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'idioma']);
    }

    public function test_el_huesped_mantiene_su_ficha_al_dia()
    {
        $user = $this->usuario('Huésped');
        $ficha = Huesped::paraUsuario($user);
        $nuevo = 'ficha' . uniqid() . '@prueba.test';

        $this->como($user)
            ->putJson('/api/perfil', $this->datos($user, ['email' => $nuevo, 'phone' => '77778888', 'current_password' => 'clave-actual']))
            ->assertOk();

        $this->assertSame($nuevo, $ficha->fresh()->email);
        $this->assertSame('77778888', $ficha->fresh()->telefono);
    }

    public function test_cambiar_la_contrasena_cierra_las_demas_sesiones()
    {
        $user = $this->usuario();
        $otra = $this->tokenDe($user);
        $actual = $this->tokenDe($user);

        $this->conToken($actual)
            ->putJson('/api/perfil/password', [
                'current_password' => 'incorrecta',
                'password' => 'clave-nueva-1',
                'password_confirmation' => 'clave-nueva-1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
        $this->assertTrue(Hash::check('clave-actual', $user->fresh()->password));

        $this->conToken($actual)
            ->putJson('/api/perfil/password', [
                'current_password' => 'clave-actual',
                'password' => 'clave-nueva-1',
                'password_confirmation' => 'clave-nueva-1',
            ])
            ->assertOk()
            ->assertJsonPath('cerradas', 1);

        $this->assertTrue(Hash::check('clave-nueva-1', $user->fresh()->password));
        $this->conToken($actual)->getJson('/api/perfil')->assertOk();
        $this->conToken($otra)->getJson('/api/perfil')->assertUnauthorized();
    }

    public function test_la_contrasena_nueva_debe_cumplir_las_reglas()
    {
        $user = $this->usuario();

        $this->como($user)
            ->putJson('/api/perfil/password', [
                'current_password' => 'clave-actual',
                'password' => 'corta',
                'password_confirmation' => 'distinta',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_cierra_otra_sesion_propia_pero_no_la_actual()
    {
        $user = $this->usuario();
        $otra = $user->createToken('spa');
        $actual = $user->createToken('spa');

        $this->conToken($actual->plainTextToken)
            ->deleteJson("/api/perfil/sesiones/{$actual->accessToken->id}")
            ->assertStatus(422);

        $this->conToken($actual->plainTextToken)
            ->deleteJson("/api/perfil/sesiones/{$otra->accessToken->id}")
            ->assertOk();

        $this->assertNull(PersonalAccessToken::find($otra->accessToken->id));
    }

    public function test_no_puede_cerrar_la_sesion_de_otra_persona()
    {
        $user = $this->usuario();
        $ajena = $this->usuario()->createToken('spa');

        $this->como($user)->deleteJson("/api/perfil/sesiones/{$ajena->accessToken->id}")->assertNotFound();

        $this->assertNotNull(PersonalAccessToken::find($ajena->accessToken->id));
    }
}
