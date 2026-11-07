<!DOCTYPE html>
<html lang="es">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Reserva cancelada</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, 'Segoe UI', Arial, sans-serif; background-color: #f4f5f7; margin: 0; padding: 0; color: #1e293b; }
        .wrapper { width: 100%; background-color: #f4f5f7; padding: 40px 15px; }
        .main { background-color: #ffffff; margin: 0 auto; max-width: 580px; border-radius: 20px; overflow: hidden; border: 1px solid #f1f5f9; }
        .header { background: #0b4f57; padding: 32px 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; }
        .content { padding: 32px 40px; line-height: 1.7; font-size: 16px; }
        .datos td { padding: 6px 0; font-size: 15px; }
        .datos td:first-child { color: #64748b; padding-right: 16px; }
        .footer { padding: 24px 40px; text-align: center; background-color: #f8fafc; font-size: 13px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="wrapper" cellpadding="0" cellspacing="0" role="presentation" width="100%">
        <tr>
            <td align="center">
                <table class="main" cellpadding="0" cellspacing="0" role="presentation" width="100%">
                    <tr><td class="header"><h1>Reserva cancelada</h1></td></tr>
                    <tr>
                        <td class="content">
                            <p>Hola, {{ $reserva->huesped->nombre_completo }}:</p>
                            <p>Cancelamos tu reserva como lo pediste.</p>
                            <table class="datos" cellpadding="0" cellspacing="0" role="presentation">
                                <tr><td>Código</td><td><strong>{{ $reserva->codigo }}</strong></td></tr>
                                <tr><td>Llegada</td><td>{{ $reserva->fecha_llegada->format('d/m/Y') }}</td></tr>
                                <tr><td>Salida</td><td>{{ $reserva->fecha_salida->format('d/m/Y') }}</td></tr>
                                <tr><td>Anticipo pagado</td><td>${{ number_format($politica['anticipo_pagado'], 2) }}</td></tr>
                                <tr><td>Reembolso</td><td>${{ number_format($politica['reembolso'], 2) }}</td></tr>
                            </table>
                            @if ($politica['reembolso'] > 0)
                                <p>El hotel procesará el reembolso por el mismo medio de pago.</p>
                            @elseif ($politica['anticipo_pagado'] > 0)
                                <p>La cancelación se hizo fuera del plazo de reembolso, así que el anticipo no se devuelve.</p>
                            @endif
                            <p>Si no pediste esta cancelación, comunícate con el hotel al {{ config('hotel.telefono') }}.</p>
                        </td>
                    </tr>
                    <tr><td class="footer">{{ $hotel }} · {{ config('hotel.direccion') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
