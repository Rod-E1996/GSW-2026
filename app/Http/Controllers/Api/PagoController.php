<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pagos\NotificacionInvalida;
use App\Services\PagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Notificacion de resultado que envia la pasarela. Sin firma valida no se toca nada;
 * si la misma notificacion llega dos veces, la segunda no cambia nada.
 */
class PagoController extends Controller
{
    public function __construct(private PagoService $pagos)
    {
    }

    public function notificacion(Request $request)
    {
        try {
            $resultado = $this->pagos->recibirNotificacion($request->getContent(), $request->header('X-Firma'));
        } catch (NotificacionInvalida $e) {
            Log::warning('Notificacion de pago rechazada', ['ip' => $request->ip(), 'motivo' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json([
            'recibido' => true,
            'repetida' => $resultado['repetida'],
        ]);
    }
}
