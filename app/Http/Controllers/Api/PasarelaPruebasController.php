<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Pagos\Pasarela;
use App\Pagos\PasarelaSandbox;
use App\Services\PagoService;
use Illuminate\Http\Request;

/**
 * Pagina de pago de la pasarela de pruebas. Hace lo que haria la pasarela real:
 * muestra el cobro, decide el resultado segun la tarjeta de prueba y envia la
 * notificacion firmada. Los datos de la tarjeta no se guardan.
 */
class PasarelaPruebasController extends Controller
{
    public function __construct(private Pasarela $pasarela, private PagoService $pagos)
    {
    }

    public function show(string $referencia)
    {
        $pago = $this->pagoDePruebas($referencia);

        return response()->json(['data' => $this->datos($pago)]);
    }

    public function pagar(Request $request, string $referencia)
    {
        $pago = $this->pagoDePruebas($referencia);

        $request->validate([
            'numero' => ['required', 'string', 'max:30', function ($campo, $valor, $falla) {
                if (!PasarelaSandbox::numeroValido($valor)) {
                    $falla('El número de tarjeta no es válido.');
                }
            }],
            'titular' => ['required', 'string', 'max:60'],
            'vencimiento' => ['required', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/', function ($campo, $valor, $falla) {
                [$mes, $anio] = explode('/', $valor);
                if (now()->startOfMonth()->gt(now()->setDate(2000 + (int) $anio, (int) $mes, 1)->startOfMonth())) {
                    $falla('La tarjeta está vencida.');
                }
            }],
            'cvv' => ['required', 'digits_between:3,4'],
        ], [
            'vencimiento.regex' => 'Escribe el vencimiento como MM/AA.',
            'cvv.digits_between' => 'El código de seguridad tiene 3 o 4 dígitos.',
        ]);

        if ((int) $pago->estado_pago !== Pago::PENDIENTE) {
            return response()->json([
                'message' => 'Este cobro ya fue procesado.',
                'data' => $this->datos($pago),
            ], 409);
        }

        //La pasarela "envia" la notificacion al hotel por el mismo camino que una real
        $cuerpo = json_encode($this->pasarela->autorizar($pago, $request->input('numero'), $request->input('titular')));
        $this->pagos->recibirNotificacion($cuerpo, $this->pasarela->firmar($cuerpo));

        return response()->json(['data' => $this->datos($pago->fresh())]);
    }

    private function pagoDePruebas(string $referencia): Pago
    {
        abort_unless($this->pasarela instanceof PasarelaSandbox, 404);

        return Pago::with('reserva')
            ->where('referencia_externa', $referencia)
            ->where('pasarela', $this->pasarela->nombre())
            ->firstOr(fn () => abort(404, 'No existe ese cobro.'));
    }

    private function datos(Pago $pago): array
    {
        return [
            'comercio' => config('hotel.nombre'),
            'concepto' => 'Anticipo de la reserva ' . $pago->reserva->codigo,
            'monto' => (float) $pago->monto,
            'moneda' => 'USD',
            'estado' => match ((int) $pago->estado_pago) {
                Pago::APROBADO => 'aprobado',
                Pago::RECHAZADO => 'rechazado',
                default => 'pendiente',
            },
            'motivo' => $pago->respuesta_pasarela['motivo'] ?? null,
            'url_retorno' => rtrim(config('app.frontend_url'), '/') . '/mi-cuenta/reservas/' . $pago->reserva->codigo
                . '?pago=' . $pago->referencia_externa,
        ];
    }
}
