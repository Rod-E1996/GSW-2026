<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Comprobante {{ $comprobante->numero }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        .cabecera { width: 100%; border-bottom: 3px solid #0b4f57; padding-bottom: 10px; margin-bottom: 16px; }
        .cabecera td { vertical-align: top; }
        .hotel { font-size: 20px; font-weight: bold; color: #0b4f57; }
        .gris { color: #64748b; }
        .titulo { font-size: 13px; font-weight: bold; text-align: right; }
        .codigo { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        h2 { font-size: 11px; color: #0b4f57; margin: 16px 0 6px; text-transform: uppercase; letter-spacing: .5px; }
        table.tabla { width: 100%; border-collapse: collapse; }
        table.tabla th { text-align: left; background: #f1f5f9; padding: 5px 6px; font-size: 10px; }
        table.tabla td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
        table.tabla .num { text-align: right; }
        .total td { font-weight: bold; border-bottom: none; }
        .datos td { padding: 3px 10px 3px 0; }
        .qr { text-align: center; }
        .qr img { width: 150px; height: 150px; }
        .aviso { margin-top: 18px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 9.5px; color: #475569; }
    </style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td>
                <div class="hotel">{{ config('hotel.nombre') }}</div>
                <div class="gris">{{ config('hotel.direccion') }}</div>
                <div class="gris">{{ config('hotel.telefono') }} · {{ config('hotel.email') }}</div>
            </td>
            <td class="titulo">
                Comprobante de reservación<br>
                <span class="gris">N.º {{ $comprobante->numero }} · versión {{ $comprobante->version }}</span><br>
                <span class="gris">Emitido el {{ $comprobante->emitido_at->format('d/m/Y H:i') }}</span>
            </td>
        </tr>
    </table>

    <table style="width:100%">
        <tr>
            <td style="vertical-align: top">
                <div class="gris">Código de confirmación</div>
                <div class="codigo">{{ $reserva->codigo }}</div>
                <table class="datos" style="margin-top: 10px">
                    <tr><td class="gris">Titular</td><td>{{ $reserva->huesped->nombre_completo }}</td></tr>
                    @if ($reserva->huesped->documento)
                        <tr><td class="gris">Documento</td><td>{{ $reserva->huesped->tipo_documento }} {{ $reserva->huesped->documento }}</td></tr>
                    @endif
                    <tr><td class="gris">Habitación</td><td>{{ $reserva->tipoHabitacion?->nombre }}{{ $reserva->habitacion ? ' · N.º ' . $reserva->habitacion->numero : '' }}</td></tr>
                    <tr><td class="gris">Llegada</td><td>{{ $reserva->fecha_llegada->format('d/m/Y') }} desde las {{ config('hotel.checkin') }}</td></tr>
                    <tr><td class="gris">Salida</td><td>{{ $reserva->fecha_salida->format('d/m/Y') }} hasta las {{ config('hotel.checkout') }}</td></tr>
                    <tr><td class="gris">Huéspedes</td><td>{{ $reserva->adultos }} {{ $reserva->adultos == 1 ? 'adulto' : 'adultos' }}@if ($reserva->ninos) y {{ $reserva->ninos }} {{ $reserva->ninos == 1 ? 'niño' : 'niños' }}@endif</td></tr>
                </table>
            </td>
            <td class="qr" style="width: 170px; vertical-align: top">
                <img src="{{ $qr }}" alt="Código QR">
                <div class="gris">Preséntalo en recepción</div>
            </td>
        </tr>
    </table>

    <h2>Detalle</h2>
    <table class="tabla">
        <tr><th>Concepto</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Importe</th></tr>
        @foreach ($reserva->noches as $noche)
            <tr>
                <td>Noche del {{ $noche->fecha->format('d/m/Y') }}{{ $noche->temporada ? ' (' . $noche->temporada->nombre . ')' : '' }}</td>
                <td class="num">1</td>
                <td class="num">${{ number_format((float) $noche->precio_noche, 2) }}</td>
                <td class="num">${{ number_format((float) $noche->precio_noche, 2) }}</td>
            </tr>
        @endforeach
        @foreach ($reserva->servicios as $servicio)
            <tr>
                <td>{{ $servicio->nombre }}{{ $servicio->pivot->fecha_servicio ? ' · ' . \Carbon\Carbon::parse($servicio->pivot->fecha_servicio)->format('d/m/Y') : '' }}</td>
                <td class="num">{{ (int) $servicio->pivot->cantidad }}</td>
                <td class="num">${{ number_format((float) $servicio->pivot->precio_unitario, 2) }}</td>
                <td class="num">${{ number_format((float) $servicio->pivot->subtotal, 2) }}</td>
            </tr>
        @endforeach
        @if ((float) $reserva->descuento > 0)
            <tr><td colspan="3">Descuento</td><td class="num">-${{ number_format((float) $reserva->descuento, 2) }}</td></tr>
        @endif
        <tr><td colspan="3">Impuestos</td><td class="num">${{ number_format((float) $reserva->impuesto, 2) }}</td></tr>
        <tr class="total"><td colspan="3">Total de la estadía</td><td class="num">${{ number_format((float) $reserva->total, 2) }}</td></tr>
        <tr><td colspan="3">Anticipo pagado</td><td class="num">${{ number_format($pagado, 2) }}</td></tr>
        <tr class="total"><td colspan="3">Saldo a pagar en el hotel</td><td class="num">${{ number_format($saldo, 2) }}</td></tr>
    </table>

    @if ($pagos->isNotEmpty())
        <h2>Pagos</h2>
        <table class="tabla">
            <tr><th>Fecha</th><th>Medio</th><th>Autorización</th><th class="num">Monto</th></tr>
            @foreach ($pagos as $pago)
                <tr>
                    <td>{{ $pago->pagado_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $pago->respuesta_pasarela['marca'] ?? ucfirst((string) $pago->metodo_pago) }}{{ isset($pago->respuesta_pasarela['ultimos4']) ? ' terminada en ' . $pago->respuesta_pasarela['ultimos4'] : '' }}</td>
                    <td>{{ $pago->respuesta_pasarela['autorizacion'] ?? '—' }}</td>
                    <td class="num">${{ number_format((float) $pago->monto, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="aviso">
        Comprobante interno de reservación: no es un documento tributario. Solo vale la versión más reciente;
        recepción comprueba su validez con el código QR.
        @if ($pagos->contains('pasarela', 'sandbox'))
            <br><strong>Pago registrado en la pasarela de pruebas: no se realizó ningún cargo real.</strong>
        @endif
    </div>
</body>
</html>
