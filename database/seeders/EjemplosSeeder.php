<?php

namespace Database\Seeders;

use App\Models\Ejemplo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Registros de la tabla "ejemplos" de la plantilla base (CRUD de muestra con
 * permisos ejemplo_*). Solo corre si la tabla esta vacia.
 */
class EjemplosSeeder extends Seeder
{
    public function run()
    {
        if (Ejemplo::count() > 0) {
            return;
        }

        //[nombre, telefono, dias atras, cantidad, porcentaje, select_quemado, accion, estado, descripcion]
        $ejemplos = [
            ['Proveedor de lavandería', '7811-3001', 120, 450.00, 15, 1, 1, 1, 'Contrato mensual de lavado de blancos.'],
            ['Mantenimiento de piscina', '7811-3002', 95, 320.50, 10, 2, 2, 1, 'Visita quincenal del técnico.'],
            ['Suministro de amenidades', '7811-3003', 80, 189.99, 5, 3, 1, 1, null],
            ['Transporte aeropuerto', '7811-3004', 64, 75.00, 20, 1, 2, 1, 'Tarifa por viaje redondo.'],
            ['Fumigación trimestral', '7811-3005', 40, 260.00, 0, 2, 1, 0, 'Servicio suspendido temporalmente.'],
            ['Floristería', '7811-3006', 22, 58.25, 12, 3, 2, 1, 'Arreglos para recepción y eventos.'],
            ['Panadería local', '7811-3007', 9, 132.40, 8, 1, 1, 1, null],
            ['Reparación de aires', '7811-3008', 2, 540.00, 25, 2, 2, 1, 'Cotización pendiente de aprobar.'],
        ];

        //Sin eventos: la carga inicial no se audita
        Model::withoutEvents(function () use ($ejemplos) {
            foreach ($ejemplos as [$nombre, $telefono, $dias, $cantidad, $porcentaje, $select, $accion, $estado, $descripcion]) {
                $fecha = now()->subDays($dias);
                $ejemplo = new Ejemplo([
                    'nombre' => $nombre,
                    'telefono' => $telefono,
                    'fecha' => $fecha->toDateString(),
                    'cantidad' => $cantidad,
                    'porcentaje' => $porcentaje,
                    'select_quemado' => $select,
                    'auditar_accion_id' => $accion,
                    'estado' => $estado,
                    'descripcion' => $descripcion,
                ]);
                $ejemplo->created_at = $fecha;
                $ejemplo->updated_at = $fecha;
                $ejemplo->save();
            }
        });
    }
}
