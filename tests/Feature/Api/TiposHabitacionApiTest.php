<?php

namespace Tests\Feature\Api;

use App\Models\Habitacion;
use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TiposHabitacionApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Bungaló Niño',
            'nombre_en' => 'Bungalow',
            'capacidad' => 3,
            'precio_base' => 80,
            'descripcion' => 'Frente al mar',
            'descripcion_en' => 'Facing the sea',
            'orden' => 4,
        ], $extra);
    }

    public function test_exige_autenticacion_y_permiso()
    {
        $this->getJson('/api/admin/tipos-habitacion')->assertUnauthorized();
        $this->como($this->huesped())->getJson('/api/admin/tipos-habitacion')->assertForbidden();
        $this->como($this->recepcion())->postJson('/api/admin/tipos-habitacion', $this->datos())->assertForbidden();
    }

    public function test_crear_guarda_los_campos_bilingues_y_genera_el_slug()
    {
        $res = $this->como($this->admin())->postJson('/api/admin/tipos-habitacion', $this->datos())->assertCreated();

        $res->assertJsonPath('data.slug', 'bungalo-nino')
            ->assertJsonPath('data.nombre_en', 'Bungalow')
            ->assertJsonPath('data.descripcion_en', 'Facing the sea')
            ->assertJsonPath('data.orden', 4);
    }

    public function test_el_slug_es_unico_aunque_el_otro_tipo_este_eliminado()
    {
        TipoHabitacion::create($this->datos(['estado' => 0]));

        $this->como($this->admin())
            ->postJson('/api/admin/tipos-habitacion', $this->datos())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'bungalo-nino-2');
    }

    public function test_renombrar_no_cambia_el_slug()
    {
        $tipo = TipoHabitacion::create($this->datos());

        $this->como($this->admin())
            ->putJson("/api/admin/tipos-habitacion/{$tipo->id}", $this->datos(['nombre' => 'Otro nombre', 'orden' => 1]))
            ->assertOk()
            ->assertJsonPath('data.slug', 'bungalo-nino')
            ->assertJsonPath('data.nombre', 'Otro nombre')
            ->assertJsonPath('data.orden', 1);
    }

    public function test_el_portal_respeta_el_orden_definido()
    {
        TipoHabitacion::query()->update(['orden' => 5]);
        $primero = TipoHabitacion::create($this->datos(['precio_base' => 900, 'orden' => 1]));

        $this->getJson('/api/tipos-habitacion')->assertOk()->assertJsonPath('data.0.id', $primero->id);
    }

    public function test_no_elimina_un_tipo_con_habitaciones_activas()
    {
        $tipo = TipoHabitacion::create($this->datos());
        Habitacion::create(['numero' => 'Z977', 'piso' => 1, 'tipo_habitacion_id' => $tipo->id]);

        $this->como($this->admin())->deleteJson("/api/admin/tipos-habitacion/{$tipo->id}")->assertStatus(422);
        $this->assertSame(1, (int) $tipo->fresh()->estado);
    }

    public function test_galeria_subir_marcar_principal_y_eliminar()
    {
        $tipo = TipoHabitacion::create($this->datos());
        $url = "/api/admin/tipos-habitacion/{$tipo->id}/imagenes";

        $this->como($this->admin())
            ->post($url, ['imagenes' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(2, 'data.imagenes');

        [$primera, $segunda] = $tipo->imagenes()->get()->all();
        $this->assertTrue($primera->principal);

        $this->como($this->admin())->putJson("$url/{$segunda->id}/principal")->assertOk();
        $this->assertTrue($segunda->fresh()->principal);
        $this->assertFalse($primera->fresh()->principal);

        $this->como($this->admin())->deleteJson("$url/{$segunda->id}")->assertOk();
        Storage::disk('public')->assertMissing($segunda->ruta);
        $this->assertTrue($primera->fresh()->principal);
    }

    public function test_no_toca_imagenes_de_otro_tipo_ni_acepta_otros_archivos()
    {
        $tipo = TipoHabitacion::create($this->datos());
        $otro = TipoHabitacion::create($this->datos(['nombre' => 'Otro tipo']));
        $imagen = TipoHabitacionImagen::create(['ruta' => 'x.jpg', 'tipo_habitacion_id' => $otro->id]);

        $this->como($this->admin())
            ->deleteJson("/api/admin/tipos-habitacion/{$tipo->id}/imagenes/{$imagen->id}")
            ->assertNotFound();

        $this->como($this->admin())
            ->post(
                "/api/admin/tipos-habitacion/{$tipo->id}/imagenes",
                ['imagenes' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]],
                ['Accept' => 'application/json']
            )
            ->assertStatus(422);
    }
}
