<?php

namespace Tests\Feature\Api;

use App\Models\AuditarDetalle;
use App\Models\Habitacion;
use App\Models\Reserva;
use App\Models\TipoHabitacion;

class HabitacionesApiTest extends ApiTestCase
{
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

    private function reservaEn(Habitacion $habitacion): Reserva
    {
        return Reserva::create([
            'codigo' => 'HL-PRUEBA-' . uniqid(),
            'huesped_id' => Reserva::firstOrFail()->huesped_id,
            'tipo_habitacion_id' => $habitacion->tipo_habitacion_id,
            'habitacion_id' => $habitacion->id,
            'fecha_llegada' => now()->addDays(5)->toDateString(),
            'fecha_salida' => now()->addDays(7)->toDateString(),
            'adultos' => 1,
            'estado_reserva' => Reserva::CONFIRMADA,
        ]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'numero' => 'Z921',
            'piso' => 9,
            'tipo_habitacion_id' => $this->tipo()->id,
            'estado_habitacion' => Habitacion::DISPONIBLE,
            'descripcion' => 'Prueba',
        ], $extra);
    }

    public function test_exige_autenticacion_y_permiso()
    {
        $this->getJson('/api/admin/habitaciones')->assertUnauthorized();
        $this->como($this->huesped())->getJson('/api/admin/habitaciones')->assertForbidden();
        $this->como($this->recepcion())->postJson('/api/admin/habitaciones', $this->datos())->assertForbidden();
    }

    public function test_lista_las_activas_con_su_tipo_y_filtra()
    {
        $tipo = $this->tipo();
        $this->habitacion(['numero' => 'Z901', 'tipo_habitacion_id' => $tipo->id, 'estado_habitacion' => Habitacion::MANTENIMIENTO]);
        $this->habitacion(['numero' => 'Z902', 'estado' => 0]);
        $this->habitacion(['numero' => 'Z903']);

        $res = $this->como($this->recepcion())
            ->getJson('/api/admin/habitaciones?numero=Z90&estado_habitacion=mantenimiento')
            ->assertOk();

        $this->assertSame(['Z901'], array_column($res->json('data'), 'numero'));
        $this->assertSame($tipo->nombre, $res->json('data.0.tipo'));
        $this->assertSame('Mantenimiento', $res->json('data.0.estado_nombre'));
    }

    public function test_los_catalogos_traen_los_estados_del_diccionario()
    {
        $res = $this->como($this->recepcion())->getJson('/api/admin/habitaciones/catalogos')->assertOk();

        $this->assertSame(['disponible', 'limpieza', 'mantenimiento'], array_column($res->json('estados'), 'valor'));
        $this->assertNotEmpty($res->json('tipos'));
    }

    public function test_crear_valida_y_deja_auditoria()
    {
        $this->como($this->admin())
            ->postJson('/api/admin/habitaciones', ['numero' => '', 'piso' => -1, 'tipo_habitacion_id' => 0, 'estado_habitacion' => 'ocupada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero', 'piso', 'tipo_habitacion_id', 'estado_habitacion']);

        $res = $this->como($this->admin())->postJson('/api/admin/habitaciones', $this->datos())->assertCreated();

        $this->assertDatabaseHas('habitaciones', ['numero' => 'Z921', 'estado' => 1]);
        $this->assertTrue(AuditarDetalle::where('primary_key', $res->json('data.id'))->where('nombre_modificado', '')->exists());
    }

    public function test_no_permite_un_numero_de_otra_activa()
    {
        $this->habitacion(['numero' => 'Z931']);

        $this->como($this->admin())
            ->postJson('/api/admin/habitaciones', $this->datos(['numero' => 'Z931']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);
    }

    public function test_crear_con_el_numero_de_una_eliminada_la_reactiva_con_sus_reservas()
    {
        $eliminada = $this->habitacion(['numero' => 'Z932', 'descripcion' => 'Vieja']);
        $reserva = $this->reservaEn($eliminada);
        $eliminada->update(['estado' => 0]);

        $res = $this->como($this->admin())
            ->postJson('/api/admin/habitaciones', $this->datos(['numero' => 'Z932', 'descripcion' => null, 'piso' => 7]))
            ->assertCreated();

        $this->assertSame($eliminada->id, $res->json('data.id'));
        $this->assertDatabaseHas('habitaciones', ['id' => $eliminada->id, 'estado' => 1, 'piso' => 7, 'descripcion' => null]);
        $this->assertSame($eliminada->id, $reserva->fresh()->habitacion_id);
        $this->assertSame(1, Habitacion::where('numero', 'Z932')->count());
    }

    public function test_editar_no_puede_tomar_el_numero_de_una_eliminada()
    {
        $this->habitacion(['numero' => 'Z933', 'estado' => 0]);
        $habitacion = $this->habitacion(['numero' => 'Z934']);

        $this->como($this->admin())
            ->putJson("/api/admin/habitaciones/{$habitacion->id}", $this->datos(['numero' => 'Z933']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);
    }

    public function test_actualizar_conserva_su_propio_numero()
    {
        $habitacion = $this->habitacion(['numero' => 'Z941']);
        $nuevoTipo = $this->tipo();

        $this->como($this->admin())
            ->putJson("/api/admin/habitaciones/{$habitacion->id}", [
                'numero' => 'Z941',
                'piso' => 2,
                'tipo_habitacion_id' => $nuevoTipo->id,
                'estado_habitacion' => Habitacion::LIMPIEZA,
            ])
            ->assertOk();

        $this->assertDatabaseHas('habitaciones', [
            'id' => $habitacion->id,
            'piso' => 2,
            'tipo_habitacion_id' => $nuevoTipo->id,
            'estado_habitacion' => 'limpieza',
        ]);
    }

    public function test_no_cambia_el_tipo_si_tiene_reservas_por_atender()
    {
        $habitacion = $this->habitacion(['numero' => 'Z942']);
        $this->reservaEn($habitacion);

        $this->como($this->admin())
            ->putJson("/api/admin/habitaciones/{$habitacion->id}", $this->datos(['numero' => 'Z942']))
            ->assertStatus(422);

        $this->assertSame($habitacion->tipo_habitacion_id, $habitacion->fresh()->tipo_habitacion_id);
    }

    public function test_recepcion_cambia_el_estado_pero_no_edita_ni_elimina()
    {
        $habitacion = $this->habitacion();

        $this->como($this->recepcion())
            ->putJson("/api/admin/habitaciones/{$habitacion->id}/estado", ['estado_habitacion' => Habitacion::LIMPIEZA])
            ->assertOk();

        $this->assertSame('limpieza', $habitacion->fresh()->estado_habitacion);

        $this->como($this->recepcion())
            ->putJson("/api/admin/habitaciones/{$habitacion->id}", $this->datos())
            ->assertForbidden();
        $this->como($this->recepcion())->deleteJson("/api/admin/habitaciones/{$habitacion->id}")->assertForbidden();
    }

    public function test_eliminar_es_logico_y_respeta_las_reservas_por_atender()
    {
        $ocupada = $this->habitacion();
        $this->reservaEn($ocupada);

        $this->como($this->admin())->deleteJson("/api/admin/habitaciones/{$ocupada->id}")->assertStatus(422);
        $this->assertSame(1, (int) $ocupada->fresh()->estado);

        $libre = $this->habitacion();
        $this->como($this->admin())->deleteJson("/api/admin/habitaciones/{$libre->id}")->assertOk();
        $this->assertDatabaseHas('habitaciones', ['id' => $libre->id, 'estado' => 0]);

        $this->como($this->admin())->getJson("/api/admin/habitaciones/{$libre->id}")->assertNotFound();
    }

    public function test_el_modulo_blade_ya_no_existe()
    {
        $this->actingAs($this->admin())->get('/habitacion')->assertNotFound();
    }

    public function test_los_permisos_de_los_formularios_blade_ya_no_existen()
    {
        $this->assertFalse(\Spatie\Permission\Models\Permission::whereIn('name', ['habitacion_create', 'habitacion_edit'])->exists());
    }
}
