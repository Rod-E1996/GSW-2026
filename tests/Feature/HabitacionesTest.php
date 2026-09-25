<?php

namespace Tests\Feature;

use App\Models\Habitacion;
use App\Models\TipoHabitacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Prueba funcional del CRUD de habitaciones.
 * Usa DatabaseTransactions: todo lo que inserta se revierte al terminar.
 * Requiere la base de datos migrada y sembrada.
 */
class HabitacionesTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::findOrFail(1);
    }

    private function recepcion(): User
    {
        return User::where('email', 'recepcion@hotellink.com')->firstOrFail();
    }

    private function tipo(): TipoHabitacion
    {
        return TipoHabitacion::create(['nombre' => 'Tipo Test ' . uniqid(), 'capacidad' => 2, 'precio_base' => 50]);
    }

    private function habitacion(array $extra = []): Habitacion
    {
        return Habitacion::create(array_merge([
            'numero' => 'T' . rand(100, 999),
            'piso' => 1,
            'tipo_habitacion_id' => $this->tipo()->id,
            'estado_habitacion' => Habitacion::DISPONIBLE,
        ], $extra));
    }

    public function test_invitado_no_puede_entrar()
    {
        $this->get('/habitacion')->assertRedirect('/login');
    }

    public function test_index_lista_las_habitaciones_con_su_tipo()
    {
        $tipo = $this->tipo();
        $this->habitacion(['numero' => 'Z901', 'tipo_habitacion_id' => $tipo->id]);
        $this->habitacion(['numero' => 'Z902', 'estado' => 0]);

        $this->actingAs($this->admin())
            ->get('/habitacion')
            ->assertOk()
            ->assertSee('Z901')
            ->assertSee($tipo->nombre)
            ->assertDontSee('Z902');
    }

    public function test_filtra_por_estado()
    {
        $this->habitacion(['numero' => 'Z911', 'estado_habitacion' => Habitacion::MANTENIMIENTO]);
        $this->habitacion(['numero' => 'Z912', 'estado_habitacion' => Habitacion::DISPONIBLE]);

        $this->actingAs($this->admin())
            ->get('/habitacion?estado_habitacion=' . Habitacion::MANTENIMIENTO)
            ->assertOk()
            ->assertSee('Z911')
            ->assertDontSee('Z912');
    }

    public function test_crear_habitacion()
    {
        $tipo = $this->tipo();

        $this->actingAs($this->admin())
            ->post('/habitacion', [
                'numero' => 'Z921',
                'piso' => 9,
                'tipo_habitacion_id' => $tipo->id,
                'estado_habitacion' => Habitacion::DISPONIBLE,
                'descripcion' => 'Prueba',
            ])
            ->assertRedirect('/habitacion')
            ->assertSessionHas('alerta', 'Agregado con éxito.');

        $this->assertDatabaseHas('habitaciones', ['numero' => 'Z921', 'piso' => 9, 'tipo_habitacion_id' => $tipo->id, 'estado' => 1]);
    }

    public function test_validacion_rechaza_datos_invalidos()
    {
        $this->actingAs($this->admin())
            ->from('/habitacion/create')
            ->post('/habitacion', [
                'numero' => '',
                'piso' => -1,
                'tipo_habitacion_id' => 999999,
                'estado_habitacion' => 7,
            ])
            ->assertRedirect('/habitacion/create')
            ->assertSessionHasErrors(['numero', 'piso', 'tipo_habitacion_id', 'estado_habitacion']);
    }

    public function test_no_permite_numero_duplicado_entre_activas()
    {
        $this->habitacion(['numero' => 'Z931']);

        $this->actingAs($this->admin())
            ->post('/habitacion', [
                'numero' => 'Z931',
                'piso' => 1,
                'tipo_habitacion_id' => $this->tipo()->id,
                'estado_habitacion' => Habitacion::DISPONIBLE,
            ])
            ->assertSessionHasErrors(['numero']);
    }

    public function test_actualizar_habitacion()
    {
        $habitacion = $this->habitacion(['numero' => 'Z941']);
        $nuevoTipo = $this->tipo();

        $this->actingAs($this->admin())
            ->put('/habitacion/' . $habitacion->id, [
                'numero' => 'Z942',
                'piso' => 2,
                'tipo_habitacion_id' => $nuevoTipo->id,
                'estado_habitacion' => Habitacion::OCUPADA,
            ])
            ->assertRedirect('/habitacion')
            ->assertSessionHas('alerta', 'Modificado con éxito.');

        $this->assertDatabaseHas('habitaciones', ['id' => $habitacion->id, 'numero' => 'Z942', 'piso' => 2, 'tipo_habitacion_id' => $nuevoTipo->id, 'estado_habitacion' => Habitacion::OCUPADA]);
    }

    public function test_recepcion_cambia_el_estado_pero_no_edita()
    {
        $habitacion = $this->habitacion(['numero' => 'Z951']);

        $this->actingAs($this->recepcion())
            ->post('/habitacion/estado/' . $habitacion->id, ['estado_habitacion' => Habitacion::MANTENIMIENTO])
            ->assertRedirect();

        $this->assertDatabaseHas('habitaciones', ['id' => $habitacion->id, 'estado_habitacion' => Habitacion::MANTENIMIENTO]);

        $this->actingAs($this->recepcion())->get('/habitacion')->assertOk();
        $this->actingAs($this->recepcion())->get('/habitacion/' . $habitacion->id)->assertOk();
        $this->actingAs($this->recepcion())->get('/habitacion/' . $habitacion->id . '/edit')->assertForbidden();
        $this->actingAs($this->recepcion())->get('/habitacion/create')->assertForbidden();
    }

    public function test_eliminar_es_logico()
    {
        $habitacion = $this->habitacion(['numero' => 'Z961']);

        $this->actingAs($this->admin())
            ->post('/habitacion/' . $habitacion->id)
            ->assertRedirect('/habitacion')
            ->assertSessionHas('alerta', 'Eliminado con éxito.');

        $this->assertDatabaseHas('habitaciones', ['id' => $habitacion->id, 'estado' => 0]);
    }

    public function test_no_se_puede_eliminar_un_tipo_con_habitaciones_activas()
    {
        $tipo = $this->tipo();
        $this->habitacion(['numero' => 'Z971', 'tipo_habitacion_id' => $tipo->id]);

        $this->actingAs($this->admin())
            ->post('/tipo_habitacion/' . $tipo->id)
            ->assertRedirect('/tipo_habitacion')
            ->assertSessionHas('tipo', 'error');

        $this->assertDatabaseHas('tipos_habitacion', ['id' => $tipo->id, 'estado' => 1]);
    }

    public function test_las_operaciones_quedan_en_auditoria()
    {
        $antes = \App\Models\AuditarDetalle::count();

        $this->actingAs($this->admin())
            ->post('/habitacion', [
                'numero' => 'Z981',
                'piso' => 1,
                'tipo_habitacion_id' => $this->tipo()->id,
                'estado_habitacion' => Habitacion::DISPONIBLE,
            ]);

        $this->assertGreaterThan($antes, \App\Models\AuditarDetalle::count());
    }
}
