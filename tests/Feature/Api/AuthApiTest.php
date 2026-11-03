<?php

namespace Tests\Feature\Api;

use App\Models\SessionLog;
use App\Models\User;
use App\Notifications\CustomResetPasswordNotification;
use App\Services\SesionTokenService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AuthApiTest extends ApiTestCase
{
    private function credenciales(string $email = 'admin@gmail.com', string $password = '12345678'): array
    {
        return ['email' => $email, 'password' => $password];
    }

    public function test_login_devuelve_token_y_registra_la_sesion()
    {
        $res = $this->postJson('/api/login', $this->credenciales());

        $res->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'roles', 'permissions', 'idioma']]);

        $tokenId = (int) explode('|', $res->json('token'))[0];
        $this->assertTrue(SessionLog::where('session_id', (string) $tokenId)->where('estado', 1)->exists());
    }

    public function test_login_rechaza_credenciales_incorrectas()
    {
        $this->postJson('/api/login', $this->credenciales('admin@gmail.com', 'incorrecta'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_rechaza_usuario_inactivo()
    {
        $this->admin()->update(['estado' => 0]);

        $this->postJson('/api/login', $this->credenciales())->assertStatus(422);
    }

    public function test_login_se_bloquea_tras_cinco_intentos_por_correo()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $this->credenciales('admin@gmail.com', 'incorrecta'))->assertStatus(422);
        }

        $this->postJson('/api/login', $this->credenciales('admin@gmail.com', 'incorrecta'))
            ->assertStatus(429)
            ->assertJsonStructure(['message']);

        // Incluso con la contrasena correcta sigue bloqueado.
        $this->postJson('/api/login', $this->credenciales())->assertStatus(429);

        // Otro correo desde la misma IP aun puede intentar.
        $this->postJson('/api/login', $this->credenciales('recepcion@hotellink.com'))->assertOk();
    }

    public function test_login_se_bloquea_tras_veinte_intentos_por_ip()
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/login', $this->credenciales("correo$i@test.com", 'incorrecta'))->assertStatus(422);
        }

        $this->postJson('/api/login', $this->credenciales('recepcion@hotellink.com'))->assertStatus(429);
    }

    public function test_me_exige_token()
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->withToken('token-inventado')->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_devuelve_el_usuario_del_token()
    {
        $this->como($this->huesped())->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'huesped@hotellink.com')
            ->assertJsonPath('user.acceso_panel', false);
    }

    public function test_logout_revoca_el_token_y_cierra_el_registro_de_sesion()
    {
        $token = $this->postJson('/api/login', $this->credenciales())->json('token');
        $tokenId = (string) explode('|', $token)[0];

        $this->conToken($token)->postJson('/api/logout')->assertOk();

        $this->assertNull(PersonalAccessToken::find($tokenId));
        $this->assertSame(0, (int) SessionLog::where('session_id', $tokenId)->value('estado'));
        $this->conToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_recordarme_define_el_tipo_de_token()
    {
        $sin = $this->postJson('/api/login', $this->credenciales() + ['recordar' => false])->json('token');
        $con = $this->postJson('/api/login', $this->credenciales() + ['recordar' => true])->json('token');
        $defecto = $this->postJson('/api/login', $this->credenciales('recepcion@hotellink.com'))->json('token');

        $this->assertSame(SesionTokenService::TOKEN_SESION, PersonalAccessToken::findToken($sin)->name);
        $this->assertSame(SesionTokenService::TOKEN_RECORDAR, PersonalAccessToken::findToken($con)->name);
        $this->assertSame(SesionTokenService::TOKEN_RECORDAR, PersonalAccessToken::findToken($defecto)->name);
    }

    public function test_token_normal_vence_por_inactividad()
    {
        $token = $this->tokenDe($this->admin());

        $this->travel((int) config('sanctum.inactividad') - 1)->minutes();
        $this->conToken($token)->getJson('/api/me')->assertOk();

        // El uso anterior reinicio el contador.
        $this->travel((int) config('sanctum.inactividad') - 1)->minutes();
        $this->conToken($token)->getJson('/api/me')->assertOk();

        $this->travel((int) config('sanctum.inactividad') + 1)->minutes();
        $this->conToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_token_de_recordarme_tolera_mas_inactividad_pero_tambien_vence()
    {
        $token = $this->tokenDe($this->admin(), true);

        $this->travel((int) config('sanctum.inactividad') + 1)->minutes();
        $this->conToken($token)->getJson('/api/me')->assertOk();

        $this->travel((int) config('sanctum.inactividad_recordar') + 1)->minutes();
        $this->conToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_ningun_token_supera_el_tope_absoluto_aunque_se_use_a_diario()
    {
        $token = $this->tokenDe($this->admin(), true);
        $tope = (int) config('sanctum.expiration');

        for ($minutos = 0; $minutos + 20160 < $tope; $minutos += 20160) {
            $this->travel(20160)->minutes();
            $this->conToken($token)->getJson('/api/me')->assertOk();
        }

        $this->travel($tope - $minutos + 1)->minutes();
        $this->conToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_las_sesiones_vencidas_no_se_cuentan_como_activas()
    {
        $admin = $this->admin();
        $servicio = app(SesionTokenService::class);

        $this->tokenDe($admin);
        $this->tokenDe($admin, true);
        $this->assertSame(2, $servicio->activasDeUsuario($admin->id)->count());

        $this->travel((int) config('sanctum.inactividad') + 1)->minutes();
        $this->assertSame(1, $servicio->activasDeUsuario($admin->id)->count());
    }

    public function test_recuperar_contrasena_envia_enlace_al_portal_react()
    {
        Notification::fake();
        $admin = $this->admin();

        $this->postJson('/api/forgot-password', ['email' => $admin->email])->assertOk();

        Notification::assertSentTo($admin, CustomResetPasswordNotification::class, function ($aviso) use ($admin) {
            $correo = $aviso->toMail($admin);

            return str_starts_with($correo->viewData['url'], config('app.frontend_url') . '/restablecer-contrasena?token=')
                && str_contains($correo->render(), 'restablecer-contrasena');
        });
    }

    public function test_recuperar_contrasena_no_revela_si_el_correo_existe()
    {
        Notification::fake();

        $existe = $this->postJson('/api/forgot-password', ['email' => 'admin@gmail.com'])->json('message');
        $noExiste = $this->postJson('/api/forgot-password', ['email' => 'nadie@test.com'])->json('message');

        $this->assertSame($existe, $noExiste);
        Notification::assertCount(1);
    }

    public function test_recuperar_contrasena_limita_los_intentos()
    {
        Notification::fake();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/forgot-password', ['email' => 'nadie@test.com'])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'nadie@test.com'])->assertStatus(429);
    }

    public function test_restablecer_cambia_la_contrasena_y_cierra_las_sesiones()
    {
        $admin = $this->admin();
        $sesion = $this->tokenDe($admin);
        $datos = [
            'token' => Password::createToken($admin),
            'email' => $admin->email,
            'password' => 'nueva-clave-1',
            'password_confirmation' => 'nueva-clave-1',
        ];

        $this->postJson('/api/reset-password', $datos)->assertOk();

        $this->assertTrue(Hash::check('nueva-clave-1', $admin->fresh()->password));
        $this->conToken($sesion)->getJson('/api/me')->assertUnauthorized();
        $this->flushHeaders();

        // El enlace es de un solo uso.
        $this->postJson('/api/reset-password', $datos)->assertStatus(422);
    }

    public function test_restablecer_rechaza_token_invalido_y_contrasena_debil()
    {
        $admin = $this->admin();

        $this->postJson('/api/reset-password', [
            'token' => 'inventado',
            'email' => $admin->email,
            'password' => 'nueva-clave-1',
            'password_confirmation' => 'nueva-clave-1',
        ])->assertStatus(422);

        $this->postJson('/api/reset-password', [
            'token' => Password::createToken($admin),
            'email' => $admin->email,
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('12345678', $admin->fresh()->password));
    }

    public function test_cors_solo_autoriza_al_portal()
    {
        $portal = $this->withHeaders(['Origin' => 'http://localhost:5173'])->getJson('/api/tipos-habitacion');
        $this->assertSame('http://localhost:5173', $portal->headers->get('Access-Control-Allow-Origin'));

        $ajeno = $this->withHeaders(['Origin' => 'https://sitio-ajeno.com'])->getJson('/api/tipos-habitacion');
        $this->assertNotSame('https://sitio-ajeno.com', $ajeno->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $ajeno->headers->get('Access-Control-Allow-Origin'));
    }
}
