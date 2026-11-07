@extends('emails.layout_reserva')

@section('titulo', 'Reserva registrada')

@section('contenido')
    <p>Registramos tu reserva. Para confirmarla falta el anticipo de <strong>${{ number_format((float) $reserva->anticipo_requerido, 2) }}</strong>.</p>
    @include('emails._datos_reserva')
    <p>Apartamos la habitación hasta el <strong>{{ $vence->format('d/m/Y H:i') }}</strong>. Si para entonces no se registra el anticipo, la reserva vence y la habitación vuelve a quedar disponible.</p>
    <p style="text-align:center"><a class="boton" href="{{ $enlace }}">Pagar el anticipo</a></p>
@endsection
