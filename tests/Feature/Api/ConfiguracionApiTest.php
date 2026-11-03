<?php

namespace Tests\Feature\Api;

use App\Models\AuditarDetalle;
use App\Models\Configuracion;

class ConfiguracionApiTest extends ApiTestCase
{
    public function test_exige_autenticacion_y_permiso()
    {
        $this->getJson('/api/admin/configuraciones')->assertUnauthorized();
        $this->como($this->recepcion())->getJson('/api/admin/configuraciones')->assertForbidden();
        $this->como($this->huesped())
            ->putJson('/api/admin/configuraciones', ['valores' => ['anticipo_porcentaje' => 50]])
            ->assertForbidden();

        $this->assertSame(30.0, Configuracion::valor('anticipo_porcentaje'));
    }

    public function test_lista_los_parametros_de_negocio()
    {
        $res = $this->como($this->admin())->getJson('/api/admin/configuraciones')->assertOk();

        $res->assertJsonStructure(['data' => [['clave', 'valor', 'tipo', 'grupo', 'descripcion']]]);
        $this->assertEqualsCanonicalizing([
            'anticipo_porcentaje',
            'impuesto_porcentaje',
            'reserva_pendiente_horas',
            'cancelacion_limite_horas',
            'cancelacion_reembolso_porcentaje',
            'no_show_reembolso_porcentaje',
        ], collect($res->json('data'))->pluck('clave')->all());
    }

    public function test_actualiza_valores_y_deja_auditoria()
    {
        $admin = $this->admin();

        $this->como($admin)->putJson('/api/admin/configuraciones', ['valores' => [
            'anticipo_porcentaje' => 25.5,
            'reserva_pendiente_horas' => 12,
        ]])->assertOk();

        $this->assertSame(25.5, Configuracion::valor('anticipo_porcentaje'));
        $this->assertSame(12.0, Configuracion::valor('reserva_pendiente_horas'));

        $parametro = Configuracion::where('clave', 'anticipo_porcentaje')->first();
        $registro = AuditarDetalle::where('primary_key', $parametro->id)->latest('id')->firstOrFail();
        $this->assertSame($admin->id, (int) $registro->user_id);
    }

    public function test_rechaza_valores_fuera_de_rango_sin_guardar_ninguno()
    {
        $admin = $this->como($this->admin());

        $admin->putJson('/api/admin/configuraciones', ['valores' => [
            'impuesto_porcentaje' => 20,
            'anticipo_porcentaje' => 101,
        ]])->assertStatus(422)->assertJsonValidationErrors('valores.anticipo_porcentaje');

        $this->assertSame(13.0, Configuracion::valor('impuesto_porcentaje'));

        $admin->putJson('/api/admin/configuraciones', ['valores' => ['reserva_pendiente_horas' => 1.5]])
            ->assertStatus(422);
        $admin->putJson('/api/admin/configuraciones', ['valores' => ['anticipo_porcentaje' => -1]])
            ->assertStatus(422);
        $admin->putJson('/api/admin/configuraciones', ['valores' => ['anticipo_porcentaje' => '']])
            ->assertStatus(422);
        $admin->putJson('/api/admin/configuraciones', ['valores' => []])->assertStatus(422);
    }

    public function test_rechaza_claves_que_no_existen()
    {
        $this->como($this->admin())
            ->putJson('/api/admin/configuraciones', ['valores' => ['clave_inventada' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('valores.clave_inventada');

        $this->assertFalse(Configuracion::where('clave', 'clave_inventada')->exists());
    }

    public function test_el_valor_por_defecto_se_usa_si_el_parametro_no_existe()
    {
        $this->assertSame(7.0, Configuracion::valor('no_existe', 7));
    }
}
