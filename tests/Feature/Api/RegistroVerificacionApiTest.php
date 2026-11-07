<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerificarCorreoNotification;
use Illuminate\Support\Facades\Notification;

class RegistroVerificacionApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombres' => 'Lucía Fernanda',
            'apellidos' => 'Ramos Díaz',
            'email' => 'lucia.ramos@test.com',
            'telefono' => '+503 7123-4567',
            'password' => 'clave-segura',
            'password_confirmation' => 'clave-segura',
            'consentimiento' => true,
        ], $extra);
    }

    //Convierte el enlace del correo en la peticion que hace la pantalla de React
    private function urlDeLaApi(string $enlace): string
    {
        parse_str(parse_url($enlace, PHP_URL_QUERY), $q);

        return "/api/correo/verificar/{$q['id']}/{$q['hash']}?expires={$q['expires']}&signature={$q['signature']}";
    }

    private function enlaceEnviado(User $user): string
    {
        $enlace = null;
        Notification::assertSentTo($user, VerificarCorreoNotification::class, function ($n) use ($user, &$enlace) {
            $enlace = VerificarCorreoNotification::enlace($user);

            return true;
        });

        return $enlace;
    }

    public function test_registro_completo_guarda_cada_dato_y_el_consentimiento()
    {
        $res = $this->postJson('/api/register', $this->datos())->assertCreated();

        $res->assertJsonPath('user.email_verificado', false);

        $user = User::where('email', 'lucia.ramos@test.com')->firstOrFail();
        $this->assertSame('Lucía Fernanda Ramos Díaz', $user->name);
        $this->assertSame('50371234567', $user->phone);
        $this->assertNotNull($user->privacidad_aceptada_at);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Lucía Fernanda', $user->huesped->nombres);
        $this->assertSame('Ramos Díaz', $user->huesped->apellidos);
        $this->assertSame('50371234567', $user->huesped->telefono);
    }

    public function test_si_el_correo_falla_la_cuenta_igual_se_crea()
    {
        Notification::swap(new class extends \Illuminate\Support\Testing\Fakes\NotificationFake {
            public function send($notifiables, $notification)
            {
                throw new \RuntimeException('SMTP caido');
            }
        });

        $this->postJson('/api/register', $this->datos())->assertCreated();

        $this->assertTrue(User::where('email', 'lucia.ramos@test.com')->exists());
    }

    public function test_sin_consentimiento_no_crea_la_cuenta()
    {
        $this->postJson('/api/register', $this->datos(['consentimiento' => false]))
            ->assertStatus(422)
            ->assertJsonPath('errors.consentimiento.0', 'Debes aceptar el aviso de privacidad para crear tu cuenta.');

        $this->assertFalse(User::where('email', 'lucia.ramos@test.com')->exists());
    }

    public function test_exige_cada_campo_y_un_telefono_que_no_sea_de_otra_cuenta()
    {
        $this->postJson('/api/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombres', 'apellidos', 'email', 'telefono', 'password', 'consentimiento']);

        User::where('email', 'recepcion@hotellink.com')->update(['phone' => '50371234567']);

        $this->postJson('/api/register', $this->datos())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);
    }

    public function test_envia_el_enlace_al_portal_react()
    {
        $this->postJson('/api/register', $this->datos())->assertCreated();
        $user = User::where('email', 'lucia.ramos@test.com')->firstOrFail();

        $enlace = $this->enlaceEnviado($user);

        $this->assertStringStartsWith('http://localhost:5173/verificar-correo?', $enlace);
    }

    public function test_el_enlace_verifica_el_correo_sin_sesion()
    {
        $this->postJson('/api/register', $this->datos())->assertCreated();
        $user = User::where('email', 'lucia.ramos@test.com')->firstOrFail();
        $this->app['auth']->forgetGuards();

        $this->getJson($this->urlDeLaApi($this->enlaceEnviado($user)))->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        //Abrirlo otra vez no falla
        $this->getJson($this->urlDeLaApi($this->enlaceEnviado($user)))->assertOk();
    }

    public function test_un_enlace_alterado_o_vencido_no_verifica()
    {
        $this->postJson('/api/register', $this->datos())->assertCreated();
        $user = User::where('email', 'lucia.ramos@test.com')->firstOrFail();
        $url = $this->urlDeLaApi($this->enlaceEnviado($user));

        $otro = User::where('email', 'huesped@hotellink.com')->firstOrFail();
        $this->getJson(str_replace("/verificar/{$user->id}/", "/verificar/{$otro->id}/", $url))->assertForbidden();
        $this->getJson(preg_replace('/signature=[a-f0-9]+/', 'signature=' . str_repeat('0', 64), $url))->assertForbidden();

        $this->travel(61)->minutes();
        $this->getJson($url)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_reenviar_el_enlace()
    {
        $this->postJson('/api/register', $this->datos())->assertCreated();
        $user = User::where('email', 'lucia.ramos@test.com')->firstOrFail();

        $this->postJson('/api/correo/reenviar')->assertUnauthorized();

        $this->como($user)->postJson('/api/correo/reenviar')->assertOk();
        Notification::assertSentToTimes($user, VerificarCorreoNotification::class, 2);

        $this->como($this->huesped())->postJson('/api/correo/reenviar')
            ->assertOk()
            ->assertJsonPath('message', 'Tu correo ya está verificado.');
    }

    public function test_el_registro_por_blade_tambien_manda_el_enlace_del_portal()
    {
        $this->post('/register', [
            'name' => 'Blade Verificar',
            'email' => 'blade.verificar@test.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);

        Notification::assertSentTo(User::where('email', 'blade.verificar@test.com')->firstOrFail(), VerificarCorreoNotification::class);
    }

    public function test_las_cuentas_de_ejemplo_vienen_verificadas()
    {
        $this->assertTrue($this->huesped()->hasVerifiedEmail());
        $this->assertTrue($this->admin()->hasVerifiedEmail());
    }
}
