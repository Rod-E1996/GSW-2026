<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Comprobante interno de la reserva (no es un documento tributario): PDF con un
 * codigo QR que recepcion escanea para verificarlo al registrar la entrada.
 */
class ComprobanteService
{
    const DISCO = 'local';

    //Emite el comprobante de una reserva confirmada. Si ya tiene uno vigente lo devuelve.
    public function emitir(Reserva $reserva): Comprobante
    {
        return $reserva->comprobanteVigente()->first() ?? $this->nuevaVersion($reserva);
    }

    //Version nueva tras una modificacion: la anterior queda sustituida y su QR deja de valer.
    public function reemitir(Reserva $reserva): Comprobante
    {
        return $this->nuevaVersion($reserva);
    }

    public function anular(Reserva $reserva): void
    {
        $reserva->comprobantes()->where('estado', Comprobante::VIGENTE)->update(['estado' => Comprobante::ANULADO]);
    }

    //Contenido del PDF; si el archivo se perdio o nunca se genero, lo vuelve a generar.
    public function pdf(Comprobante $comprobante): string
    {
        if (!$comprobante->ruta_pdf || !Storage::disk(self::DISCO)->exists($comprobante->ruta_pdf)) {
            $this->generarPdf($comprobante);
        }

        return Storage::disk(self::DISCO)->get($comprobante->ruta_pdf);
    }

    //Lo que contiene el QR: la pantalla de verificacion del panel (pide sesion de recepcion)
    public function urlVerificacion(Comprobante $comprobante): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/panel/verificar/' . $comprobante->qr_token;
    }

    public function qrSvg(Comprobante $comprobante): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'addQuietzone' => true,
        ])))->render($this->urlVerificacion($comprobante));
    }

    //Busca por el token del QR o por el codigo de la reserva (si el QR no se puede leer)
    public function buscar(string $valor): ?Comprobante
    {
        $valor = trim($valor);

        //Acepta tambien la URL completa que trae el QR
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $valor, $m)) {
            return Comprobante::where('qr_token', strtolower($m[0]))->first();
        }

        $reserva = Reserva::where('codigo', strtoupper($valor))->first();

        return $reserva?->comprobantes()->reorder('version', 'desc')->first();
    }

    //Recepcion confirma que vio el comprobante; la primera verificacion es la que queda.
    public function marcarVerificado(Comprobante $comprobante, User $user): Comprobante
    {
        if (!$comprobante->verificado_at) {
            $comprobante->update(['verificado_at' => now(), 'verificado_por' => $user->id]);
        }

        return $comprobante->fresh();
    }

    private function nuevaVersion(Reserva $reserva): Comprobante
    {
        $comprobante = DB::transaction(function () use ($reserva) {
            //Bloquea la reserva para que dos emisiones simultaneas no tomen la misma version
            Reserva::whereKey($reserva->id)->lockForUpdate()->first();

            $version = (int) $reserva->comprobantes()->max('version') + 1;
            $reserva->comprobantes()->where('estado', Comprobante::VIGENTE)->update(['estado' => Comprobante::SUSTITUIDO]);

            return $reserva->comprobantes()->create([
                'numero' => 'CMP-' . substr($reserva->codigo, 3) . '-' . $version,
                'version' => $version,
                'qr_token' => (string) Str::uuid(),
                'emitido_at' => now(),
                'estado' => Comprobante::VIGENTE,
            ]);
        });

        //Si el PDF falla, el comprobante ya existe y el PDF se genera al descargarlo
        try {
            $this->generarPdf($comprobante);
        } catch (\Throwable $e) {
            report($e);
        }

        return $comprobante->fresh();
    }

    private function generarPdf(Comprobante $comprobante): void
    {
        $reserva = $comprobante->reserva()
            ->with(['huesped', 'tipoHabitacion', 'habitacion', 'noches.temporada', 'servicios', 'pagos'])
            ->first();

        $qr = (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 6,
            'addQuietzone' => true,
        ])))->render($this->urlVerificacion($comprobante));

        $aprobados = $reserva->pagos->where('estado_pago', Pago::APROBADO);
        $pagado = round((float) $aprobados->sum('monto'), 2);

        $contenido = Pdf::loadView('comprobantes.reserva', [
            'comprobante' => $comprobante,
            'reserva' => $reserva,
            'qr' => $qr,
            'pagos' => $aprobados,
            'pagado' => $pagado,
            'saldo' => max(0, round((float) $reserva->total - $pagado, 2)),
        ])
            //Solo se incrustan las letras usadas: el PDF pesa decenas de KB y no casi 1 MB
            ->setOption('isFontSubsettingEnabled', true)
            ->setPaper('letter')
            ->output();

        $ruta = 'comprobantes/' . $comprobante->numero . '.pdf';
        Storage::disk(self::DISCO)->put($ruta, $contenido);
        $comprobante->update(['ruta_pdf' => $ruta]);
    }
}
