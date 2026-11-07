<?php

namespace App\Pagos;

use App\Models\Pago;
use Illuminate\Support\Str;

/**
 * Pasarela de pruebas interna: imita a una pasarela real (pagina de pago propia,
 * resultado por tarjeta de prueba y notificacion firmada) sin mover dinero.
 */
class PasarelaSandbox implements Pasarela
{
    //Tarjetas de prueba con resultado fijo; cualquier otra tarjeta valida se aprueba
    const TARJETAS_RECHAZADAS = [
        '4000000000000002' => 'Tarjeta rechazada por el banco emisor.',
        '4000000000009995' => 'Fondos insuficientes.',
    ];

    public function nombre(): string
    {
        return 'sandbox';
    }

    public function urlDePago(Pago $pago): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/pasarela-pruebas/' . $pago->referencia_externa;
    }

    //Decide el resultado como lo haria el banco y arma la notificacion que la pasarela envia
    public function autorizar(Pago $pago, string $numero, string $titular): array
    {
        $numero = preg_replace('/\D/', '', $numero);
        $motivo = self::TARJETAS_RECHAZADAS[$numero] ?? null;

        return [
            'referencia' => $pago->referencia_externa,
            'estado' => $motivo ? 'rechazado' : 'aprobado',
            'motivo' => $motivo,
            'autorizacion' => $motivo ? null : strtoupper(Str::random(6)),
            'monto' => number_format((float) $pago->monto, 2, '.', ''),
            'moneda' => 'USD',
            'marca' => $this->marca($numero),
            'ultimos4' => substr($numero, -4),
            'titular' => mb_substr(trim($titular), 0, 60),
            'fecha' => now()->toIso8601String(),
            'ambiente' => 'pruebas',
        ];
    }

    public function firmar(string $cuerpo): string
    {
        return hash_hmac('sha256', $cuerpo, $this->secreto());
    }

    public function leerNotificacion(string $cuerpo, ?string $firma): array
    {
        if (!$firma || !hash_equals($this->firmar($cuerpo), $firma)) {
            throw new NotificacionInvalida('Firma de la notificación inválida.');
        }

        $datos = json_decode($cuerpo, true);

        if (!is_array($datos) || empty($datos['referencia']) || !in_array($datos['estado'] ?? null, ['aprobado', 'rechazado'], true)
            || !isset($datos['monto'])) {
            throw new NotificacionInvalida('La notificación no trae los datos esperados.');
        }

        return [
            'referencia' => (string) $datos['referencia'],
            'aprobado' => $datos['estado'] === 'aprobado',
            'monto' => (float) $datos['monto'],
            'respuesta' => $datos,
        ];
    }

    //Algoritmo de Luhn: descarta numeros mal digitados antes de "enviarlos al banco"
    public static function numeroValido(string $numero): bool
    {
        $numero = preg_replace('/\D/', '', $numero);
        if (strlen($numero) < 13 || strlen($numero) > 19) {
            return false;
        }

        $suma = 0;
        foreach (array_reverse(str_split($numero)) as $i => $digito) {
            $d = (int) $digito;
            if ($i % 2 === 1) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $suma += $d;
        }

        return $suma % 10 === 0;
    }

    private function marca(string $numero): string
    {
        return match (true) {
            str_starts_with($numero, '4') => 'Visa',
            (bool) preg_match('/^(5[1-5]|2[2-7])/', $numero) => 'Mastercard',
            (bool) preg_match('/^3[47]/', $numero) => 'American Express',
            default => 'Tarjeta',
        };
    }

    private function secreto(): string
    {
        return config('services.pasarela.secreto') ?: hash('sha256', 'pasarela-sandbox|' . config('app.key'));
    }
}
