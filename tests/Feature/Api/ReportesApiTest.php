<?php

namespace Tests\Feature\Api;

use App\Models\CategoriaServicio;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\TipoHabitacion;

/**
 * Reporte de ocupacion e ingresos: los totales cuadran con un calculo a mano.
 * Usa un tipo de habitacion propio en un periodo sin otras reservas.
 */
class ReportesApiTest extends ApiTestCase
{
    private TipoHabitacion $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tipo = TipoHabitacion::create(['nombre' => 'Cabaña de reporte', 'capacidad' => 2, 'precio_base' => 100]);
        $r1 = Habitacion::create(['numero' => 'R-901', 'piso' => 9, 'tipo_habitacion_id' => $this->tipo->id]);
        $r2 = Habitacion::create(['numero' => 'R-902', 'piso' => 9, 'tipo_habitacion_id' => $this->tipo->id]);
        $categoria = CategoriaServicio::create(['nombre' => 'Categoría de reporte', 'orden' => 99]);
        $kayak = Servicio::create(['categoria_servicio_id' => $categoria->id, 'nombre' => 'Kayak de reporte', 'precio' => 15, 'forma_cobro' => Servicio::POR_PERSONA]);

        // A confirmada: 2 noches a $100 y un anticipo de $60 cobrado en el periodo (otro de abril queda fuera)
        $a = $this->reserva($r1, Reserva::CONFIRMADA, ['2040-03-01' => 100, '2040-03-02' => 100]);
        $this->pago($a, 60, '2040-03-01 10:00:00');
        $this->pago($a, 999, '2040-04-15 10:00:00');

        // B finalizada: 3 noches a $80 y kayak x2 ($30)
        $b = $this->reserva($r2, Reserva::FINALIZADA, ['2040-03-05' => 80, '2040-03-06' => 80, '2040-03-07' => 80]);
        $b->servicios()->attach($kayak->id, ['cantidad' => 2, 'precio_unitario' => 15, 'subtotal' => 30, 'fecha_servicio' => '2040-03-05', 'estado_servicio' => 1]);

        // C pendiente y D cancelada: no cuentan
        $this->reserva($r1, Reserva::PENDIENTE, ['2040-03-03' => 100]);
        $this->reserva($r2, Reserva::CANCELADA, ['2040-03-08' => 80, '2040-03-09' => 80]);
    }

    private function reserva(Habitacion $habitacion, int $estado, array $noches): Reserva
    {
        $fechas = array_keys($noches);
        $reserva = Reserva::create([
            'codigo' => 'HL-R-' . uniqid(),
            'huesped_id' => Huesped::create(['nombres' => 'Reporte', 'apellidos' => 'Prueba'])->id,
            'tipo_habitacion_id' => $habitacion->tipo_habitacion_id,
            'habitacion_id' => $habitacion->id,
            'fecha_llegada' => $fechas[0],
            'fecha_salida' => date('Y-m-d', strtotime(end($fechas) . ' +1 day')),
            'adultos' => 2,
            'estado_reserva' => $estado,
        ]);
        foreach ($noches as $fecha => $precio) {
            $reserva->noches()->create(['habitacion_id' => $habitacion->id, 'fecha' => $fecha, 'precio_noche' => $precio]);
        }

        return $reserva;
    }

    private function pago(Reserva $reserva, float $monto, string $pagadoAt): void
    {
        Pago::create(['reserva_id' => $reserva->id, 'tipo_pago' => Pago::ANTICIPO, 'metodo_pago' => 'efectivo', 'monto' => $monto, 'estado_pago' => Pago::APROBADO, 'pagado_at' => $pagadoAt]);
    }

    public function test_solo_quien_tiene_permiso_consulta_el_reporte()
    {
        $this->getJson('/api/admin/reportes?desde=2040-03-01&hasta=2040-03-10')->assertUnauthorized();
        $this->como($this->recepcion())->getJson('/api/admin/reportes?desde=2040-03-01&hasta=2040-03-10')->assertForbidden();
    }

    public function test_los_totales_cuadran_con_el_calculo_a_mano()
    {
        $res = $this->como($this->admin())
            ->getJson("/api/admin/reportes?desde=2040-03-01&hasta=2040-03-10&tipo_habitacion_id={$this->tipo->id}")
            ->assertOk();

        $r = $res->json('data.resumen');
        $this->assertSame(20, $r['noches_disponibles']);         // 2 habitaciones x 10 dias
        $this->assertSame(5, $r['noches_vendidas']);             // A 2 + B 3 (C pendiente y D cancelada no)
        $this->assertEquals(25.0, $r['ocupacion']);              // 5 / 20
        $this->assertEquals(440.0, $r['ingreso_alojamiento']);   // 200 + 240
        $this->assertEquals(88.0, $r['adr']);                    // 440 / 5
        $this->assertEquals(22.0, $r['revpar']);                 // 440 / 20
        $this->assertEquals(30.0, $r['ingreso_servicios']);
        $this->assertEquals(60.0, $r['ingresos_cobrados']);

        $this->assertCount(10, $res->json('data.serie'));
        $this->assertSame('Kayak de reporte', $res->json('data.servicios.0.nombre'));
    }

    public function test_exporta_el_reporte_a_pdf()
    {
        $res = $this->como($this->admin())
            ->get("/api/admin/reportes/pdf?desde=2040-03-01&hasta=2040-03-10&tipo_habitacion_id={$this->tipo->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $res->getContent());
    }
}
