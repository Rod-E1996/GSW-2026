@extends('emails.layout_reserva')

@section('titulo', 'Reserva cancelada')

@section('contenido')
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
    <p>Tu comprobante quedó anulado. Si no pediste esta cancelación, comunícate con el hotel cuanto antes.</p>
@endsection
