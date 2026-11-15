@php
    $r = $reporte['resumen'];
    $dinero = fn ($v) => '$' . number_format((float) $v, 2);
    $fecha = fn ($v) => \Carbon\Carbon::parse($v)->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Reporte de ocupación e ingresos</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1e293b; }
        .cabecera { width: 100%; border-bottom: 3px solid #0b4f57; padding-bottom: 10px; margin-bottom: 14px; }
        .hotel { font-size: 20px; font-weight: bold; color: #0b4f57; }
        .gris { color: #64748b; }
        h2 { font-size: 11px; color: #0b4f57; margin: 16px 0 6px; text-transform: uppercase; }
        table.tabla { width: 100%; border-collapse: collapse; }
        table.tabla th { text-align: left; background: #f1f5f9; padding: 5px 6px; font-size: 9.5px; }
        table.tabla td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td>
                <div class="hotel">{{ config('hotel.nombre') }}</div>
                <div class="gris">{{ config('hotel.direccion') }}</div>
            </td>
            <td style="text-align: right">
                <strong>Reporte de ocupación e ingresos</strong><br>
                <span class="gris">Del {{ $fecha($reporte['periodo']['desde']) }} al {{ $fecha($reporte['periodo']['hasta']) }}</span><br>
                <span class="gris">{{ $reporte['tipo'] ?? 'Todos los tipos de habitación' }}</span>
            </td>
        </tr>
    </table>

    <h2>Resumen</h2>
    <table class="tabla">
        <tr><td>Ocupación</td><td class="num">{{ number_format($r['ocupacion'], 1) }} %</td></tr>
        <tr><td>Noches vendidas / disponibles</td><td class="num">{{ $r['noches_vendidas'] }} / {{ $r['noches_disponibles'] }}</td></tr>
        <tr><td>ADR (tarifa media por noche vendida)</td><td class="num">{{ $dinero($r['adr']) }}</td></tr>
        <tr><td>RevPAR (ingreso por noche disponible)</td><td class="num">{{ $dinero($r['revpar']) }}</td></tr>
        <tr><td>Ingreso por alojamiento</td><td class="num">{{ $dinero($r['ingreso_alojamiento']) }}</td></tr>
        <tr><td>Ingreso por servicios</td><td class="num">{{ $dinero($r['ingreso_servicios']) }}</td></tr>
        <tr><td>Cobrado en el período</td><td class="num">{{ $dinero($r['ingresos_cobrados']) }}</td></tr>
    </table>

    <h2>Por día</h2>
    <table class="tabla">
        <tr><th>Día</th><th class="num">Vendidas</th><th class="num">Ocupación</th><th class="num">Ingreso</th></tr>
        @foreach ($reporte['serie'] as $dia)
            <tr>
                <td>{{ $fecha($dia['fecha']) }}</td>
                <td class="num">{{ $dia['noches_vendidas'] }} / {{ $dia['noches_disponibles'] }}</td>
                <td class="num">{{ number_format($dia['ocupacion'], 1) }} %</td>
                <td class="num">{{ $dinero($dia['ingreso_alojamiento']) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Servicios más solicitados</h2>
    @if (count($reporte['servicios']))
        <table class="tabla">
            <tr><th>Servicio</th><th class="num">Cantidad</th><th class="num">Ingreso</th></tr>
            @foreach ($reporte['servicios'] as $servicio)
                <tr><td>{{ $servicio['nombre'] }}</td><td class="num">{{ $servicio['cantidad'] }}</td><td class="num">{{ $dinero($servicio['ingreso']) }}</td></tr>
            @endforeach
        </table>
    @else
        <p class="gris">No se contrataron servicios en el período.</p>
    @endif

    <p class="gris" style="margin-top: 16px">Generado el {{ $generado->format('d/m/Y H:i') }}.</p>
</body>
</html>
