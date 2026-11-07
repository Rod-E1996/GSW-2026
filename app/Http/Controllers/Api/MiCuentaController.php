<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Models\Pago;
use App\Services\CancelacionService;
use App\Services\ComprobanteService;
use App\Services\PagoService;
use Illuminate\Http\Request;

/**
 * Area privada del huesped: su ficha y sus reservas. Nunca muestra reservas de otra cuenta.
 */
class MiCuentaController extends Controller
{
    public function __construct(
        private CancelacionService $cancelaciones,
        private PagoService $pagos,
        private ComprobanteService $comprobantes
    ) {
    }

    //Datos con que se precarga la pantalla de reserva.
    public function huesped(Request $request)
    {
        $ficha = Huesped::paraUsuario($request->user());

        return response()->json([
            'data' => $ficha->only(['nombres', 'apellidos', 'tipo_documento', 'documento', 'email', 'telefono']),
        ]);
    }

    public function reservas(Request $request)
    {
        $reservas = $this->propias($request)
            ->with(['tipoHabitacion', 'habitacion'])
            ->orderByDesc('fecha_llegada')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $reservas->map(fn (Reserva $r) => $this->resumen($r)),
        ]);
    }

    public function reserva(Request $request, string $codigo)
    {
        $reserva = $this->propias($request)
            ->with(['tipoHabitacion', 'habitacion', 'noches.temporada', 'servicios', 'historial', 'huesped'])
            ->where('codigo', $codigo)
            ->first();

        if (!$reserva) {
            return response()->json(['message' => 'No encontramos esa reserva en tu cuenta.'], 404);
        }

        return response()->json(['data' => $this->detalle($reserva)]);
    }

    public function cancelar(Request $request, string $codigo)
    {
        $datos = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $reserva = $this->propias($request)->where('codigo', $codigo)->first();

        if (!$reserva) {
            return response()->json(['message' => 'No encontramos esa reserva en tu cuenta.'], 404);
        }

        $resultado = $this->cancelaciones->cancelar($reserva, $datos['motivo'] ?? null);
        $politica = $resultado['politica'];

        return response()->json([
            'message' => $politica['reembolso'] > 0
                ? 'Reserva cancelada. Se te reembolsarán $' . number_format($politica['reembolso'], 2) . '. Te enviamos la confirmación por correo.'
                : 'Reserva cancelada. Te enviamos la confirmación por correo.',
            'data' => $this->detalle($resultado['reserva']->fresh(['tipoHabitacion', 'habitacion', 'noches.temporada', 'servicios', 'historial', 'huesped'])),
        ]);
    }

    //Abre el pago del anticipo y devuelve la pagina de la pasarela.
    public function pagar(Request $request, string $codigo)
    {
        $reserva = $this->propias($request)->where('codigo', $codigo)->first();

        if (!$reserva) {
            return response()->json(['message' => 'No encontramos esa reserva en tu cuenta.'], 404);
        }

        $intento = $this->pagos->iniciar($reserva, $request->user());

        return response()->json([
            'data' => [
                'referencia' => $intento['pago']->referencia_externa,
                'monto' => (float) $intento['pago']->monto,
                'url' => $intento['url'],
            ],
        ]);
    }

    public function comprobante(Request $request, string $codigo)
    {
        $reserva = $this->propias($request)->where('codigo', $codigo)->first();
        $comprobante = $reserva?->comprobanteVigente()->first();

        if (!$comprobante) {
            return response()->json(['message' => 'Esta reserva no tiene un comprobante vigente.'], 404);
        }

        return response($this->comprobantes->pdf($comprobante), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="comprobante-' . $reserva->codigo . '.pdf"',
        ]);
    }

    //Solo las reservas cuya ficha pertenece a la cuenta autenticada
    private function propias(Request $request)
    {
        return Reserva::where('estado', 1)
            ->whereHas('huesped', fn ($q) => $q->where('user_id', $request->user()->id));
    }

    private function resumen(Reserva $r): array
    {
        $vencida = (int) $r->estado_reserva === Reserva::PENDIENTE
            && !Reserva::vigentes()->whereKey($r->id)->exists();

        return [
            'codigo' => $r->codigo,
            'estado' => $r->estado_reserva,
            'estado_nombre' => $vencida ? 'Pendiente vencida' : $r->estado_nombre,
            'vencida' => $vencida,
            'tipo' => $r->tipoHabitacion?->nombre,
            'habitacion' => $r->habitacion?->numero,
            'fecha_llegada' => $r->fecha_llegada->toDateString(),
            'fecha_salida' => $r->fecha_salida->toDateString(),
            'noches' => $r->noches_total,
            'total' => (float) $r->total,
        ];
    }

    private function detalle(Reserva $r): array
    {
        $pagos = $r->pagos()->get();
        $pagado = round((float) $pagos->where('estado_pago', Pago::APROBADO)->sum('monto'), 2);
        $comprobante = $r->comprobanteVigente()->first();
        $resumen = $this->resumen($r);

        return $resumen + [
            'pagos' => $pagos->map(fn (Pago $p) => [
                'referencia' => $p->referencia_externa,
                'monto' => (float) $p->monto,
                'estado' => $p->estado_pago,
                'estado_nombre' => $p->estado_nombre,
                'medio' => isset($p->respuesta_pasarela['ultimos4'])
                    ? ($p->respuesta_pasarela['marca'] ?? 'Tarjeta') . ' terminada en ' . $p->respuesta_pasarela['ultimos4']
                    : null,
                'motivo' => $p->respuesta_pasarela['motivo'] ?? null,
                'pasarela' => $p->pasarela,
                'fecha' => ($p->pagado_at ?? $p->updated_at)?->toIso8601String(),
                'requiere_reembolso' => PagoService::requiereReembolso($p),
            ])->values(),
            'pagado' => $pagado,
            'saldo' => max(0, round((float) $r->total - $pagado, 2)),
            //Una pendiente vencida aun se puede pagar si la habitacion sigue libre (lo revisa PagoService)
            'puede_pagar' => (int) $r->estado_reserva === Reserva::PENDIENTE && $pagado <= 0,
            'comprobante' => $comprobante ? [
                'numero' => $comprobante->numero,
                'version' => $comprobante->version,
                'emitido_at' => $comprobante->emitido_at?->toIso8601String(),
                'qr' => $this->comprobantes->qrSvg($comprobante),
            ] : null,
            'adultos' => $r->adultos,
            'ninos' => $r->ninos,
            'hora_estimada_llegada' => $r->hora_estimada_llegada ? substr($r->hora_estimada_llegada, 0, 5) : null,
            'notas' => $r->notas,
            'huesped' => $r->huesped->only(['nombres', 'apellidos', 'tipo_documento', 'documento', 'email', 'telefono']),
            'subtotal_alojamiento' => (float) $r->subtotal_alojamiento,
            'subtotal_servicios' => (float) $r->subtotal_servicios,
            'descuento' => (float) $r->descuento,
            'impuesto' => (float) $r->impuesto,
            'anticipo_requerido' => (float) $r->anticipo_requerido,
            'detalle_noches' => $r->noches->map(fn ($n) => [
                'fecha' => $n->fecha->toDateString(),
                'temporada' => $n->temporada?->nombre,
                'precio_noche' => (float) $n->precio_noche,
            ]),
            'servicios' => $r->servicios->map(fn ($s) => [
                'nombre' => $s->nombre,
                'cantidad' => (int) $s->pivot->cantidad,
                'precio_unitario' => (float) $s->pivot->precio_unitario,
                'subtotal' => (float) $s->pivot->subtotal,
                'fecha_servicio' => $s->pivot->fecha_servicio,
                'hora_servicio' => $s->pivot->hora_servicio ? substr($s->pivot->hora_servicio, 0, 5) : null,
            ]),
            'historial' => $r->historial->map(fn ($h) => [
                'estado' => Reserva::ESTADOS[$h->estado_nuevo]['nombre'] ?? 'Desconocido',
                'comentario' => $h->comentario,
                'fecha' => $h->created_at?->toIso8601String(),
            ]),
            'cancelada_at' => $r->cancelada_at?->toIso8601String(),
            'motivo_cancelacion' => $r->motivo_cancelacion,
            'cancelacion' => $this->cancelaciones->evaluar($r),
        ];
    }
}
