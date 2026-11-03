<?php

namespace Tests\Feature\Api;

use App\Models\CategoriaServicio;
use App\Models\Servicio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ServiciosApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function categoria(array $extra = []): CategoriaServicio
    {
        return CategoriaServicio::create(array_merge(['nombre' => 'Categoria ' . uniqid()], $extra));
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'categoria_servicio_id' => $this->categoria()->id,
            'nombre' => 'Spa de prueba',
            'nombre_en' => 'Test spa',
            'descripcion' => 'Masajes',
            'descripcion_en' => 'Massages',
            'icono' => 'bi-flower1',
            'precio' => 25.5,
            'forma_cobro' => Servicio::POR_PERSONA,
            'requiere_fecha' => true,
        ], $extra);
    }

    public function test_recepcion_solo_consulta()
    {
        $this->getJson('/api/admin/servicios')->assertUnauthorized();
        $this->como($this->huesped())->getJson('/api/admin/servicios')->assertForbidden();

        $this->como($this->recepcion())->getJson('/api/admin/servicios')->assertOk();
        $this->como($this->recepcion())->getJson('/api/admin/categorias-servicio')->assertOk();
        $this->como($this->recepcion())->postJson('/api/admin/servicios', $this->datos())->assertForbidden();
        $this->como($this->recepcion())->postJson('/api/admin/categorias-servicio', ['nombre' => 'X'])->assertForbidden();
    }

    public function test_los_datos_de_ejemplo_tienen_categoria()
    {
        $this->assertSame(0, Servicio::whereNull('categoria_servicio_id')->count());
        $this->assertSame('Alimentación y bebidas', Servicio::where('nombre', 'Alimentación')->first()->categoria->nombre);
    }

    public function test_crud_de_categorias()
    {
        $id = $this->como($this->admin())
            ->postJson('/api/admin/categorias-servicio', ['nombre' => 'Bienestar', 'nombre_en' => 'Wellness', 'orden' => 9])
            ->assertCreated()
            ->json('data.id');

        $this->como($this->admin())
            ->postJson('/api/admin/categorias-servicio', ['nombre' => 'Bienestar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        $this->como($this->admin())
            ->putJson("/api/admin/categorias-servicio/$id", ['nombre' => 'Bienestar', 'orden' => 2])
            ->assertOk()
            ->assertJsonPath('data.orden', 2);

        $this->como($this->admin())->deleteJson("/api/admin/categorias-servicio/$id")->assertOk();
        $this->assertDatabaseHas('categorias_servicio', ['id' => $id, 'estado' => 0]);
    }

    public function test_no_elimina_una_categoria_con_servicios_activos()
    {
        $categoria = $this->categoria();
        $servicio = Servicio::create($this->datos(['categoria_servicio_id' => $categoria->id]));

        $this->como($this->admin())->deleteJson("/api/admin/categorias-servicio/{$categoria->id}")->assertStatus(422);

        $listado = $this->como($this->admin())->getJson('/api/admin/categorias-servicio')->assertOk();
        $this->assertSame(1, collect($listado->json('data'))->firstWhere('id', $categoria->id)['servicios_count']);

        $servicio->update(['estado' => 0]);
        $this->como($this->admin())->deleteJson("/api/admin/categorias-servicio/{$categoria->id}")->assertOk();
    }

    public function test_crear_servicio_con_todos_los_campos()
    {
        $res = $this->como($this->admin())->postJson('/api/admin/servicios', $this->datos())->assertCreated();

        $res->assertJsonPath('data.nombre_en', 'Test spa')
            ->assertJsonPath('data.requiere_fecha', true)
            ->assertJsonPath('data.forma_cobro_nombre', 'Por persona');
    }

    public function test_valida_categoria_forma_de_cobro_y_nombre_repetido()
    {
        $eliminada = $this->categoria(['estado' => 0]);

        $this->como($this->admin())
            ->postJson('/api/admin/servicios', $this->datos([
                'categoria_servicio_id' => $eliminada->id,
                'forma_cobro' => 'gratis',
                'precio' => -1,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['categoria_servicio_id', 'forma_cobro', 'precio']);

        $this->como($this->admin())
            ->postJson('/api/admin/servicios', $this->datos(['nombre' => 'Transporte']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_filtra_por_categoria_y_eliminar_es_logico()
    {
        $categoria = $this->categoria();
        $servicio = Servicio::create($this->datos(['categoria_servicio_id' => $categoria->id]));

        $res = $this->como($this->admin())
            ->getJson("/api/admin/servicios?categoria_servicio_id={$categoria->id}")
            ->assertOk();
        $this->assertSame([$servicio->id], array_column($res->json('data'), 'id'));
        $this->assertCount(4, $res->json('formas_cobro'));

        $this->como($this->admin())->deleteJson("/api/admin/servicios/{$servicio->id}")->assertOk();
        $this->assertDatabaseHas('servicios', ['id' => $servicio->id, 'estado' => 0]);
        $this->como($this->admin())->getJson("/api/admin/servicios/{$servicio->id}")->assertNotFound();
    }

    public function test_subir_reemplazar_y_quitar_la_imagen()
    {
        $servicio = Servicio::create($this->datos());

        $this->como($this->admin())
            ->post("/api/admin/servicios/{$servicio->id}/imagen", ['imagen' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertOk();
        $primera = $servicio->fresh()->imagen;
        Storage::disk('public')->assertExists($primera);

        $this->como($this->admin())
            ->post("/api/admin/servicios/{$servicio->id}/imagen", ['imagen' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])
            ->assertOk();
        $segunda = $servicio->fresh()->imagen;
        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($segunda);

        $this->como($this->admin())->deleteJson("/api/admin/servicios/{$servicio->id}/imagen")->assertOk();
        Storage::disk('public')->assertMissing($segunda);
        $this->assertNull($servicio->fresh()->imagen);
    }

    public function test_rechaza_archivos_que_no_son_imagen()
    {
        $servicio = Servicio::create($this->datos());

        $this->como($this->admin())
            ->post(
                "/api/admin/servicios/{$servicio->id}/imagen",
                ['imagen' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')],
                ['Accept' => 'application/json']
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['imagen']);
    }
}
