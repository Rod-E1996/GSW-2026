<table class="datos" cellpadding="0" cellspacing="0" role="presentation">
    <tr><td>Código</td><td><strong>{{ $reserva->codigo }}</strong></td></tr>
    <tr><td>Habitación</td><td>{{ $reserva->tipoHabitacion?->nombre }}{{ $reserva->habitacion ? ' · ' . $reserva->habitacion->numero : '' }}</td></tr>
    <tr><td>Llegada</td><td>{{ $reserva->fecha_llegada->format('d/m/Y') }} desde las {{ config('hotel.checkin') }}</td></tr>
    <tr><td>Salida</td><td>{{ $reserva->fecha_salida->format('d/m/Y') }} hasta las {{ config('hotel.checkout') }}</td></tr>
    <tr><td>Total</td><td>${{ number_format((float) $reserva->total, 2) }}</td></tr>
</table>
