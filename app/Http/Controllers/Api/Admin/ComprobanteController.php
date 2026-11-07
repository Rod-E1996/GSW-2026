<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Pago;
use App\Models\Reserva;
use App\Services\ComprobanteService;
use Illuminate\Http\Request;

/**
 * Verificacion del comprobante en recepcion: por el QR o por el codigo de la reserva.
 */
class ComprobanteController extends Controller
{
    public function __construct(private ComprobanteService $comprobantes)
    {
    }

    public function show(string $valor)
    {
        $comprobante = $this->comprobantes->buscar($valor);

        if (!$comprobante) {
            return response()->json(['message' => 'No existe un comprobante con ese código.'], 404);
        }

        return response()->json(['data' => $this->datos($comprobante)]);
    }

    //Recepcion deja constancia de que vio el comprobante
    public function verificar(Request $request, string $valor)
    {
        $comprobante = $this->comprobantes->buscar($valor);

        if (!$comprobante) {
            return response()->json(['message' => 'No existe un comprobante con ese código.'], 404);
        }

        $datos = $this->datos($comprobante);
        if (!$datos['valido']) {
            return response()->json(['message' => $datos['mensaje'], 'data' => $datos], 422);
        }

        $comprobante = $this->comprobantes->marcarVerificado($comprobante, $request->user());

        return response()->json([
            'message' => 'Comprobante verificado.',
            'data' => $this->datos($comprobante),
        ]);
    }

    public function pdf(string $valor)
    {
        $comprobante = $this->comprobantes->buscar($valor);

        if (!$comprobante) {
            return response()->json(['message' => 'No existe un comprobante con ese código.'], 404);
        }

        return response($this->comprobantes->pdf($comprobante), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $comprobante->numero . '.pdf"',
        ]);
    }

    private function datos(Comprobante $comprobante): array
    {
        $comprobante->loadMissing(['reserva.huesped', 'reserva.tipoHabitacion', 'reserva.habitacion', 'verificador']);
        $reserva = $comprobante->reserva;
        $pagado = round((float) $reserva->pagos()->where('estado_pago', Pago::APROBADO)->sum('monto'), 2);

        [$valido, $mensaje] = match (true) {
            (int) $comprobante->estado === Comprobante::ANULADO => [false, 'Comprobante anulado: la reserva fue cancelada.'],
            (int) $comprobante->estado === Comprobante::SUSTITUIDO => [false, 'Este comprobante fue reemplazado por una versión más reciente.'],
            (int) $reserva->estado_reserva === Reserva::CANCELADA => [false, 'La reserva está cancelada.'],
            (int) $reserva->estado_reserva === Reserva::FINALIZADA => [false, 'La estadía de esta reserva ya terminó.'],
            !in_array((int) $reserva->estado_reserva, [Reserva::CONFIRMADA, Reserva::EN_ESTADIA]) => [false, 'La reserva no está confirmada.'],
            default => [true, 'Comprobante válido.'],
        };

        return [
            'valido' => $valido,
            'mensaje' => $mensaje,
            'comprobante' => [
                'numero' => $comprobante->numero,
                'version' => $comprobante->version,
                'estado' => $comprobante->estado_nombre,
                'qr_token' => $comprobante->qr_token,
                'emitido_at' => $comprobante->emitido_at?->toIso8601String(),
                'verificado_at' => $comprobante->verificado_at?->toIso8601String(),
                'verificado_por' => $comprobante->verificador?->name,
            ],
            'reserva' => [
                'codigo' => $reserva->codigo,
                'estado' => $reserva->estado_reserva,
                'estado_nombre' => $reserva->estado_nombre,
                'titular' => $reserva->huesped?->nombre_completo,
                'documento' => trim(($reserva->huesped?->tipo_documento ?? '') . ' ' . ($reserva->huesped?->documento ?? '')),
                'telefono' => $reserva->huesped?->telefono,
                'tipo' => $reserva->tipoHabitacion?->nombre,
                'habitacion' => $reserva->habitacion?->numero,
                'fecha_llegada' => $reserva->fecha_llegada->toDateString(),
                'fecha_salida' => $reserva->fecha_salida->toDateString(),
                'noches' => $reserva->noches_total,
                'adultos' => $reserva->adultos,
                'ninos' => $reserva->ninos,
                'total' => (float) $reserva->total,
                'pagado' => $pagado,
                'saldo' => max(0, round((float) $reserva->total - $pagado, 2)),
            ],
        ];
    }
}
