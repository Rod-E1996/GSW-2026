<?php

namespace Tests\Feature\Api;

use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Models\Valoracion;

/**
 * Valoraciones: el huesped valora su estadia finalizada una vez, el hotel la modera
 * y solo las aprobadas se publican con su promedio.
 */
class ValoracionesApiTest extends ApiTestCase
{
    private function reservaDelHuesped(int $estado): Reserva
    {
        $habitacion = Habitacion::where('estado', 1)->firstOrFail();

        return Reserva::create([
            'codigo' => 'HL-V-' . uniqid(),
            'huesped_id' => Huesped::paraUsuario($this->huesped())->id,
            'tipo_habitacion_id' => $habitacion->tipo_habitacion_id,
            'habitacion_id' => $habitacion->id,
            'fecha_llegada' => '2026-01-10',
            'fecha_salida' => '2026-01-12',
            'adultos' => 2,
            'estado_reserva' => $estado,
        ]);
    }

    public function test_solo_se_valora_una_estadia_finalizada_propia_y_una_vez()
    {
        $finalizada = $this->reservaDelHuesped(Reserva::FINALIZADA);
        $confirmada = $this->reservaDelHuesped(Reserva::CONFIRMADA);
        $url = fn (Reserva $r) => "/api/mi-cuenta/reservas/{$r->codigo}/valoracion";

        $this->como($this->huesped())->postJson($url($confirmada), ['puntuacion' => 5])->assertStatus(422);
        $this->como($this->recepcion())->postJson($url($finalizada), ['puntuacion' => 5])->assertNotFound();
        $this->como($this->huesped())->postJson($url($finalizada), ['puntuacion' => 9])->assertStatus(422)->assertJsonValidationErrors('puntuacion');

        $this->como($this->huesped())->postJson($url($finalizada), ['puntuacion' => 4, 'comentario' => 'Muy buena atención'])
            ->assertCreated()
            ->assertJsonPath('data.estado', Valoracion::PENDIENTE);

        $this->como($this->huesped())->postJson($url($finalizada), ['puntuacion' => 5])->assertStatus(422);
        $this->como($this->huesped())->getJson("/api/mi-cuenta/reservas/{$finalizada->codigo}")
            ->assertJsonPath('data.puede_valorar', false)
            ->assertJsonPath('data.valoracion.puntuacion', 4);
    }

    public function test_solo_las_aprobadas_se_publican_con_su_promedio()
    {
        $reserva = $this->reservaDelHuesped(Reserva::FINALIZADA);
        $this->como($this->huesped())->postJson("/api/mi-cuenta/reservas/{$reserva->codigo}/valoracion", ['puntuacion' => 4, 'comentario' => 'Volveremos'])->assertCreated();
        $valoracion = Valoracion::where('reserva_id', $reserva->id)->firstOrFail();

        $this->assertNotContains($valoracion->id, $this->getJson('/api/valoraciones')->json('data.*.id'));

        //Recepcion no modera; el administrador si
        $this->como($this->recepcion())->getJson('/api/admin/valoraciones')->assertForbidden();
        $this->como($this->admin())->putJson("/api/admin/valoraciones/{$valoracion->id}", ['estado' => Valoracion::APROBADA, 'respuesta' => '¡Gracias por visitarnos!'])
            ->assertOk()
            ->assertJsonPath('data.estado', Valoracion::APROBADA);

        $publicas = $this->getJson('/api/valoraciones')->assertOk();
        $this->assertContains($valoracion->id, $publicas->json('data.*.id'));
        $this->assertEquals(round(Valoracion::where('estado', Valoracion::APROBADA)->avg('puntuacion'), 1), $publicas->json('promedio'));
        $this->assertSame('¡Gracias por visitarnos!', collect($publicas->json('data'))->firstWhere('id', $valoracion->id)['respuesta']);
    }
}
