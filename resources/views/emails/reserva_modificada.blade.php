@extends('emails.layout_reserva')

@section('titulo', 'Reserva modificada')

@section('contenido')
    <p>Hicimos estos cambios en tu reserva {{ $reserva->codigo }}:</p>
    <table class="datos" cellpadding="0" cellspacing="0" role="presentation">
        @foreach ($cambios as $cambio)
            <tr><td>{{ $cambio['campo'] }}</td><td>{{ $cambio['antes'] }} → <strong>{{ $cambio['despues'] }}</strong></td></tr>
        @endforeach
    </table>
    <p>Así queda tu reserva:</p>
    @include('emails._datos_reserva')
    <p>Si tienes un comprobante impreso, descarga la versión nueva desde Mi cuenta.</p>
    <p style="text-align:center"><a class="boton" href="{{ $enlace }}">Ver mi reserva</a></p>
    <p class="nota">Si no pediste este cambio, comunícate con el hotel.</p>
@endsection
