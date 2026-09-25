<?php

namespace Tests\Feature;

use App\Models\TipoHabitacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Prueba funcional del CRUD de tipos de habitación.
 * Usa DatabaseTransactions: todo lo que inserta se revierte al terminar.
 * Requiere la base de datos migrada y sembrada (usuario id 1 = Super Administrador).
 */
class TiposHabitacionTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::findOrFail(1);
    }

    public function test_invitado_no_puede_entrar()
    {
        $this->get('/tipo_habitacion')->assertRedirect('/login');
    }

    public function test_index_lista_los_tipos_activos()
    {
        $tipo = TipoHabitacion::create(['nombre' => 'Prueba Index', 'capacidad' => 2, 'precio_base' => 40]);
        $inactivo = TipoHabitacion::create(['nombre' => 'Prueba Inactiva', 'capacidad' => 2, 'precio_base' => 40, 'estado' => 0]);

        $this->actingAs($this->admin())
            ->get('/tipo_habitacion')
            ->assertOk()
            ->assertSee('Prueba Index')
            ->assertDontSee('Prueba Inactiva');
    }

    public function test_crear_tipo_de_habitacion()
    {
        $this->actingAs($this->admin())
            ->post('/tipo_habitacion', [
                'nombre' => 'Suite Presidencial',
                'capacidad' => 3,
                'precio_base' => 199.99,
                'descripcion' => 'La mejor del hotel',
            ])
            ->assertRedirect('/tipo_habitacion')
            ->assertSessionHas('alerta', 'Agregado con éxito.');

        $this->assertDatabaseHas('tipos_habitacion', [
            'nombre' => 'Suite Presidencial',
            'capacidad' => 3,
            'precio_base' => 199.99,
            'estado' => 1,
        ]);
    }

    public function test_validacion_rechaza_datos_invalidos()
    {
        $this->actingAs($this->admin())
            ->from('/tipo_habitacion/create')
            ->post('/tipo_habitacion', [
                'nombre' => '',
                'capacidad' => 0,
                'precio_base' => -5,
            ])
            ->assertRedirect('/tipo_habitacion/create')
            ->assertSessionHasErrors(['nombre', 'capacidad', 'precio_base']);
    }

    public function test_no_permite_nombre_duplicado_entre_activos()
    {
        TipoHabitacion::create(['nombre' => 'Duplicada', 'capacidad' => 2, 'precio_base' => 40]);

        $this->actingAs($this->admin())
            ->post('/tipo_habitacion', ['nombre' => 'Duplicada', 'capacidad' => 2, 'precio_base' => 40])
            ->assertSessionHasErrors(['nombre']);
    }

    public function test_show_y_edit_muestran_el_registro()
    {
        $tipo = TipoHabitacion::create(['nombre' => 'Ver Tipo', 'capacidad' => 2, 'precio_base' => 40, 'descripcion' => 'Con balcón']);

        $this->actingAs($this->admin())
            ->get('/tipo_habitacion/' . $tipo->id)
            ->assertOk()
            ->assertSee('Ver Tipo')
            ->assertSee('Con balcón');

        $this->actingAs($this->admin())
            ->get('/tipo_habitacion/' . $tipo->id . '/edit')
            ->assertOk()
            ->assertSee('Ver Tipo');
    }

    public function test_actualizar_tipo_de_habitacion()
    {
        $tipo = TipoHabitacion::create(['nombre' => 'Original', 'capacidad' => 1, 'precio_base' => 30]);

        $this->actingAs($this->admin())
            ->put('/tipo_habitacion/' . $tipo->id, [
                'nombre' => 'Modificada',
                'capacidad' => 2,
                'precio_base' => 45.50,
            ])
            ->assertRedirect('/tipo_habitacion')
            ->assertSessionHas('alerta', 'Modificado con éxito.');

        $this->assertDatabaseHas('tipos_habitacion', ['id' => $tipo->id, 'nombre' => 'Modificada', 'capacidad' => 2, 'precio_base' => 45.50]);
    }

    public function test_eliminar_es_logico()
    {
        $tipo = TipoHabitacion::create(['nombre' => 'Para Borrar', 'capacidad' => 1, 'precio_base' => 30]);

        $this->actingAs($this->admin())
            ->post('/tipo_habitacion/' . $tipo->id)
            ->assertRedirect('/tipo_habitacion')
            ->assertSessionHas('alerta', 'Eliminado con éxito.');

        $this->assertDatabaseHas('tipos_habitacion', ['id' => $tipo->id, 'estado' => 0]);

        $this->actingAs($this->admin())
            ->get('/tipo_habitacion/' . $tipo->id)
            ->assertRedirect('/tipo_habitacion');
    }

    public function test_las_operaciones_quedan_en_auditoria()
    {
        $antes = \App\Models\AuditarDetalle::count();

        $this->actingAs($this->admin())
            ->post('/tipo_habitacion', ['nombre' => 'Auditada', 'capacidad' => 2, 'precio_base' => 40]);

        $this->assertGreaterThan($antes, \App\Models\AuditarDetalle::count());
    }
}
