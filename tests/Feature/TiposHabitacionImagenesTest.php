<?php

namespace Tests\Feature;

use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Prueba la carga y administración de fotos de un tipo de habitación.
 * Usa un disco "public" falso, así que no escribe archivos reales.
 */
class TiposHabitacionImagenesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::findOrFail(1);
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['nombre' => 'Con Fotos ' . uniqid(), 'capacidad' => 2, 'precio_base' => 60], $extra);
    }

    public function test_crear_tipo_con_fotos_guarda_archivos_y_marca_la_primera_como_principal()
    {
        $this->actingAs($this->admin())
            ->post('/tipo_habitacion', $this->datos([
                'imagenes' => [
                    UploadedFile::fake()->image('cama.jpg', 800, 600),
                    UploadedFile::fake()->image('bano.png', 800, 600),
                ],
            ]))
            ->assertRedirect('/tipo_habitacion');

        $tipo = TipoHabitacion::latest('id')->first();
        $imagenes = $tipo->imagenes()->orderBy('orden')->get();

        $this->assertCount(2, $imagenes);
        $this->assertTrue($imagenes[0]->principal);
        $this->assertFalse($imagenes[1]->principal);
        $this->assertEquals('cama.jpg', $imagenes[0]->nombre_original);

        foreach ($imagenes as $imagen) {
            Storage::disk('public')->assertExists($imagen->ruta);
        }
    }

    public function test_rechaza_archivos_que_no_son_imagen()
    {
        $this->actingAs($this->admin())
            ->from('/tipo_habitacion/create')
            ->post('/tipo_habitacion', $this->datos([
                'imagenes' => [UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf')],
            ]))
            ->assertRedirect('/tipo_habitacion/create')
            ->assertSessionHasErrors(['imagenes.0']);
    }

    public function test_editar_agrega_fotos_sin_cambiar_la_principal()
    {
        $tipo = TipoHabitacion::create($this->datos());
        $principal = TipoHabitacionImagen::create(['tipo_habitacion_id' => $tipo->id, 'ruta' => 'x/a.jpg', 'orden' => 1, 'principal' => true]);

        $this->actingAs($this->admin())
            ->put('/tipo_habitacion/' . $tipo->id, $this->datos([
                'nombre' => $tipo->nombre,
                'imagenes' => [UploadedFile::fake()->image('nueva.jpg')],
            ]))
            ->assertRedirect('/tipo_habitacion');

        $this->assertEquals(2, $tipo->imagenes()->count());
        $this->assertEquals(1, $tipo->imagenes()->where('principal', true)->count());
        $this->assertTrue($principal->fresh()->principal);
    }

    public function test_marcar_otra_imagen_como_principal()
    {
        $tipo = TipoHabitacion::create($this->datos());
        $a = TipoHabitacionImagen::create(['tipo_habitacion_id' => $tipo->id, 'ruta' => 'x/a.jpg', 'orden' => 1, 'principal' => true]);
        $b = TipoHabitacionImagen::create(['tipo_habitacion_id' => $tipo->id, 'ruta' => 'x/b.jpg', 'orden' => 2, 'principal' => false]);

        $this->actingAs($this->admin())
            ->post('/tipo_habitacion/' . $tipo->id . '/imagen/' . $b->id . '/principal')
            ->assertRedirect();

        $this->assertFalse($a->fresh()->principal);
        $this->assertTrue($b->fresh()->principal);
    }

    public function test_eliminar_la_principal_promueve_la_siguiente_y_borra_el_archivo()
    {
        $tipo = TipoHabitacion::create($this->datos());
        Storage::disk('public')->put('x/a.jpg', 'contenido');
        $a = TipoHabitacionImagen::create(['tipo_habitacion_id' => $tipo->id, 'ruta' => 'x/a.jpg', 'orden' => 1, 'principal' => true]);
        $b = TipoHabitacionImagen::create(['tipo_habitacion_id' => $tipo->id, 'ruta' => 'x/b.jpg', 'orden' => 2, 'principal' => false]);

        $this->actingAs($this->admin())
            ->post('/tipo_habitacion/' . $tipo->id . '/imagen/' . $a->id . '/eliminar')
            ->assertRedirect();

        $this->assertDatabaseMissing('tipo_habitacion_imagenes', ['id' => $a->id]);
        Storage::disk('public')->assertMissing('x/a.jpg');
        $this->assertTrue($b->fresh()->principal);
    }

    public function test_no_se_puede_tocar_una_imagen_de_otro_tipo()
    {
        $tipoA = TipoHabitacion::create($this->datos());
        $tipoB = TipoHabitacion::create($this->datos());
        $imagenB = TipoHabitacionImagen::create(['tipo_habitacion_id' => $tipoB->id, 'ruta' => 'x/b.jpg', 'orden' => 1, 'principal' => true]);

        $this->actingAs($this->admin())
            ->post('/tipo_habitacion/' . $tipoA->id . '/imagen/' . $imagenB->id . '/eliminar')
            ->assertSessionHas('tipo', 'error');

        $this->assertDatabaseHas('tipo_habitacion_imagenes', ['id' => $imagenB->id]);
    }

    public function test_show_y_edit_muestran_la_galeria()
    {
        $tipo = TipoHabitacion::create($this->datos());
        TipoHabitacionImagen::create(['tipo_habitacion_id' => $tipo->id, 'ruta' => 'x/foto.jpg', 'orden' => 1, 'principal' => true]);

        $this->actingAs($this->admin())->get('/tipo_habitacion/' . $tipo->id)->assertOk()->assertSee('storage/x/foto.jpg')->assertSee('Principal');
        $this->actingAs($this->admin())->get('/tipo_habitacion/' . $tipo->id . '/edit')->assertOk()->assertSee('storage/x/foto.jpg')->assertSee('Agregar fotos');
    }
}
