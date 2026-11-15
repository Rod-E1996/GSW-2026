<?php

namespace Tests\Feature\Api;

use App\Models\Respaldo;
use Illuminate\Support\Facades\Storage;

/**
 * Respaldos desde el panel: solo quien tiene permiso los genera, lista y descarga.
 */
class RespaldosApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_exigen_autenticacion_y_permiso()
    {
        $this->getJson('/api/admin/respaldos')->assertUnauthorized();
        $this->como($this->recepcion())->getJson('/api/admin/respaldos')->assertForbidden();
        $this->como($this->recepcion())->postJson('/api/admin/respaldos')->assertForbidden();
    }

    public function test_generar_listar_y_descargar_un_respaldo()
    {
        $id = $this->como($this->admin())->postJson('/api/admin/respaldos')
            ->assertCreated()
            ->assertJsonPath('data.origen', Respaldo::MANUAL)
            ->json('data.id');

        $respaldo = Respaldo::findOrFail($id);
        $contenido = Storage::disk('local')->get('respaldos/' . $respaldo->archivo);

        //Trae la estructura y los datos de las tablas
        $this->assertStringContainsString('CREATE TABLE', $contenido);
        $this->assertStringContainsString('INSERT INTO `users`', $contenido);
        $this->assertStringContainsString('admin@gmail.com', $contenido);
        $this->assertSame(strlen($contenido), (int) $respaldo->tamano);

        $this->como($this->admin())->getJson('/api/admin/respaldos')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->como($this->admin())->get("/api/admin/respaldos/$id/descargar")->assertOk();
    }

    public function test_la_tarea_programada_genera_un_respaldo()
    {
        $this->artisan('respaldos:generar')->assertSuccessful();

        $this->assertSame(Respaldo::PROGRAMADO, Respaldo::latest('id')->value('origen'));
    }
}
