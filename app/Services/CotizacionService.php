<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Servicio;
use App\Models\TipoHabitacion;
use Illuminate\Validation\ValidationException;

/**
 * Resumen de una reserva: alojamiento, servicios, impuesto, total y anticipo.
 * Lo usan la pantalla de reserva (antes de confirmar) y ReservaService (al guardar).
 */
class CotizacionService
{
    public function __construct(private PrecioService $precios)
    {
    }

    /**
     * $pedidos: [['servicio_id' => 1, 'cantidad' => 2, 'fecha_servicio' => 'AAAA-MM-DD', 'hora_servicio' => 'HH:MM'], ...]
     * La cantidad solo cuenta en los servicios de precio fijo; los demas se calculan por personas y noches.
     */
    public function calcular(TipoHabitacion $tipo, string $llegada, string $salida, int $personas, array $pedidos = []): array
    {
        $alojamiento = $this->precios->cotizar($tipo, $llegada, $salida);
        $servicios = $this->servicios($pedidos, $llegada, $salida, $personas, count($alojamiento['noches']));

        $subtotalServicios = round(array_sum(array_column($servicios, 'subtotal')), 2);
        $impuesto = round(($alojamiento['subtotal'] + $subtotalServicios) * Configuracion::valor('impuesto_porcentaje') / 100, 2);
        $total = round($alojamiento['subtotal'] + $subtotalServicios + $impuesto, 2);

        return [
            'noches' => $alojamiento['noches'],
            'precio_promedio' => $alojamiento['precio_promedio'],
            'servicios' => $servicios,
            'subtotal_alojamiento' => $alojamiento['subtotal'],
            'subtotal_servicios' => $subtotalServicios,
            'descuento' => 0.0,
            'impuesto' => $impuesto,
            'total' => $total,
            'anticipo_requerido' => round($total * Configuracion::valor('anticipo_porcentaje') / 100, 2),
            'impuesto_porcentaje' => Configuracion::valor('impuesto_porcentaje'),
            'anticipo_porcentaje' => Configuracion::valor('anticipo_porcentaje'),
        ];
    }

    private function servicios(array $pedidos, string $llegada, string $salida, int $personas, int $noches): array
    {
        $catalogo = Servicio::where('estado', 1)
            ->whereIn('id', array_column($pedidos, 'servicio_id'))
            ->get()
            ->keyBy('id');

        $lineas = [];

        foreach (array_values($pedidos) as $i => $pedido) {
            $servicio = $catalogo->get($pedido['servicio_id']);

            if (!$servicio) {
                throw ValidationException::withMessages([
                    "servicios.$i.servicio_id" => 'Ese servicio ya no está disponible.',
                ]);
            }

            $fecha = $pedido['fecha_servicio'] ?? null;

            if ($servicio->requiere_fecha && (!$fecha || $fecha < $llegada || $fecha > $salida)) {
                throw ValidationException::withMessages([
                    "servicios.$i.fecha_servicio" => "Indica una fecha para «{$servicio->nombre}» dentro de tu estadía.",
                ]);
            }

            $cantidad = match ($servicio->forma_cobro) {
                Servicio::POR_PERSONA_NOCHE => $personas * $noches,
                Servicio::POR_PERSONA => $personas,
                Servicio::POR_NOCHE => $noches,
                default => max(1, (int) ($pedido['cantidad'] ?? 1)),
            };

            $lineas[] = [
                'servicio_id' => $servicio->id,
                'nombre' => $servicio->nombre,
                'forma_cobro' => $servicio->forma_cobro_nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => (float) $servicio->precio,
                'subtotal' => round($cantidad * (float) $servicio->precio, 2),
                'fecha_servicio' => $servicio->requiere_fecha ? $fecha : null,
                'hora_servicio' => $servicio->requiere_fecha ? ($pedido['hora_servicio'] ?? null) : null,
            ];
        }

        return $lineas;
    }
}
