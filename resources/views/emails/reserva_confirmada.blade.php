@extends('emails.layout_reserva')

@section('titulo', 'Reserva confirmada')

@section('contenido')
    <p>Recibimos tu anticipo de <strong>${{ number_format($pagado, 2) }}</strong> y tu reserva quedó confirmada.</p>
    @include('emails._datos_reserva')
    <p>Saldo a pagar en el hotel: <strong>${{ number_format(max(0, (float) $reserva->total - $pagado), 2) }}</strong>.</p>
    @if ($comprobante)
        <p>Adjuntamos tu comprobante {{ $comprobante->numero }}. Preséntalo al llegar, impreso o en el teléfono: su código QR agiliza el registro de entrada.</p>
    @else
        <p>Puedes descargar tu comprobante desde Mi cuenta.</p>
    @endif
    <p style="text-align:center"><a class="boton" href="{{ $enlace }}">Ver mi reserva</a></p>
@endsection
