<?php

namespace Tests\Feature\Api;

use App\Models\Tarifa;
use App\Models\Temporada;
use App\Models\TipoHabitacion;

class TarifasApiTest extends ApiTestCase
{
    private function temporada(array $extra = []): Temporada
    {
        return Temporada::create(array_merge([
            'nombre' => 'Prueba ' . uniqid(),
            'fecha_inicio' => now()->addDays(10)->toDateString(),
            'fecha_fin' => now()->addDays(20)->toDateString(),
            'multiplicador' => 1.2,
        ], $extra));
    }

    private function tipo(): TipoHabitacion
    {
        return TipoHabitacion::create(['nombre' => 'Tarifa ' . uniqid(), 'capacidad' => 2, 'precio_base' => 100]);
    }

    private function datosTemporada(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Alta',
            'nombre_en' => 'High',
            'fecha_inicio' => '2030-01-01',
            'fecha_fin' => '2030-01-31',
            'multiplicador' => 1.5,
            'prioridad' => 3,
            'color' => '#AA11BB',
        ], $extra);
    }

    public function test_recepcion_solo_consulta()
    {
        $this->getJson('/api/admin/temporadas')->assertUnauthorized();
        $this->como($this->huesped())->getJson('/api/admin/tarifas')->assertForbidden();

        $this->como($this->recepcion())->getJson('/api/admin/temporadas')->assertOk();
        $this->como($this->recepcion())->getJson('/api/admin/tarifas')->assertOk();
        $this->como($this->recepcion())->postJson('/api/admin/temporadas', $this->datosTemporada())->assertForbidden();
        $this->como($this->recepcion())->putJson('/api/admin/tarifas', ['tarifas' => []])->assertForbidden();
    }

    public function test_crear_temporada_con_todos_los_campos()
    {
        $res = $this->como($this->admin())->postJson('/api/admin/temporadas', $this->datosTemporada())->assertCreated();

        $res->assertJsonPath('data.nombre_en', 'High')
            ->assertJsonPath('data.prioridad', 3)
            ->assertJsonPath('data.color', '#AA11BB')
            ->assertJsonPath('data.fecha_inicio', '2030-01-01');
    }

    public function test_valida_fechas_color_y_multiplicador()
    {
        $this->como($this->admin())
            ->postJson('/api/admin/temporadas', $this->datosTemporada([
                'fecha_fin' => '2029-12-31',
                'color' => 'rojo',
                'multiplicador' => 0,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_fin', 'color', 'multiplicador']);
    }

    public function test_la_prioridad_decide_entre_temporadas_traslapadas()
    {
        $fecha = '2040-06-15';
        $datos = ['fecha_inicio' => '2040-06-01', 'fecha_fin' => '2040-06-30'];

        $this->temporada($datos + ['multiplicador' => 2.0, 'prioridad' => 1]);
        $prioritaria = $this->temporada($datos + ['multiplicador' => 1.1, 'prioridad' => 5]);

        $this->assertSame($prioritaria->id, Temporada::vigentePara($fecha)->id);
    }

    public function test_editar_y_eliminar_temporada()
    {
        $temporada = $this->temporada();

        $this->como($this->admin())
            ->putJson("/api/admin/temporadas/{$temporada->id}", $this->datosTemporada(['nombre' => 'Cambiada']))
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Cambiada');

        $this->como($this->admin())->deleteJson("/api/admin/temporadas/{$temporada->id}")->assertOk();
        $this->assertDatabaseHas('temporadas', ['id' => $temporada->id, 'estado' => 0]);
        $this->como($this->admin())->getJson("/api/admin/temporadas/{$temporada->id}")->assertNotFound();
    }

    public function test_la_matriz_guarda_edita_y_quita_precios()
    {
        $tipo = $this->tipo();
        $temporada = $this->temporada();
        $celda = ['tipo_habitacion_id' => $tipo->id, 'temporada_id' => $temporada->id];

        $this->como($this->admin())
            ->putJson('/api/admin/tarifas', ['tarifas' => [$celda + ['precio_noche' => 130]]])
            ->assertOk();
        $this->assertDatabaseHas('tarifas', $celda + ['precio_noche' => 130, 'estado' => 1]);

        $this->como($this->admin())
            ->putJson('/api/admin/tarifas', ['tarifas' => [$celda + ['precio_noche' => 150.5]]])
            ->assertOk();
        $this->assertSame(1, Tarifa::where($celda)->count());
        $this->assertDatabaseHas('tarifas', $celda + ['precio_noche' => 150.5]);

        //Vaciar la celda retira la tarifa
        $res = $this->como($this->admin())
            ->putJson('/api/admin/tarifas', ['tarifas' => [$celda + ['precio_noche' => null]]])
            ->assertOk();
        $this->assertDatabaseHas('tarifas', $celda + ['estado' => 0]);
        $this->assertEmpty(collect($res->json('tarifas'))->where('temporada_id', $temporada->id)->all());

        //Y volver a escribirla la reactiva sin duplicar
        $this->como($this->admin())
            ->putJson('/api/admin/tarifas', ['tarifas' => [$celda + ['precio_noche' => 90]]])
            ->assertOk();
        $this->assertDatabaseHas('tarifas', $celda + ['precio_noche' => 90, 'estado' => 1]);
        $this->assertSame(1, Tarifa::where($celda)->count());
    }

    public function test_una_celda_vacia_sin_tarifa_previa_no_crea_nada()
    {
        $celda = ['tipo_habitacion_id' => $this->tipo()->id, 'temporada_id' => $this->temporada()->id];

        $this->como($this->admin())
            ->putJson('/api/admin/tarifas', ['tarifas' => [$celda + ['precio_noche' => null]]])
            ->assertOk();

        $this->assertSame(0, Tarifa::where($celda)->count());
    }

    public function test_si_una_celda_es_invalida_no_se_guarda_ninguna()
    {
        $tipo = $this->tipo();
        $temporada = $this->temporada();
        $otra = $this->temporada();

        $this->como($this->admin())
            ->putJson('/api/admin/tarifas', ['tarifas' => [
                ['tipo_habitacion_id' => $tipo->id, 'temporada_id' => $temporada->id, 'precio_noche' => 100],
                ['tipo_habitacion_id' => $tipo->id, 'temporada_id' => $otra->id, 'precio_noche' => -1],
            ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tarifas.1.precio_noche']);

        $this->assertSame(0, Tarifa::where('tipo_habitacion_id', $tipo->id)->count());
    }

    public function test_no_acepta_tipos_ni_temporadas_eliminados()
    {
        $tipo = $this->tipo();
        $temporada = $this->temporada(['estado' => 0]);

        $this->como($this->admin())
            ->putJson('/api/admin/tarifas', ['tarifas' => [
                ['tipo_habitacion_id' => $tipo->id, 'temporada_id' => $temporada->id, 'precio_noche' => 100],
            ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tarifas.0.temporada_id']);
    }

    public function test_la_matriz_omite_las_temporadas_terminadas()
    {
        $vigente = $this->temporada();
        $pasada = $this->temporada([
            'fecha_inicio' => now()->subDays(20)->toDateString(),
            'fecha_fin' => now()->subDays(10)->toDateString(),
        ]);

        $ids = array_column($this->como($this->admin())->getJson('/api/admin/tarifas')->assertOk()->json('temporadas'), 'id');

        $this->assertContains($vigente->id, $ids);
        $this->assertNotContains($pasada->id, $ids);
    }
}
