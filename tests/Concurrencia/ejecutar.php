<?php

/**
 * Prueba de concurrencia real: procesos separados compiten por las mismas habitaciones
 * sobre MariaDB. SQLite no bloquea filas, por eso no forma parte de las pruebas de PHPUnit.
 *
 * Borra la base que usa, asi que necesita una propia. Con Docker, una sola vez:
 *   docker compose exec base sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS hotellink_concurrencia; GRANT ALL ON hotellink_concurrencia.* TO \"$MARIADB_USER\"@\"%\""'
 * Y cada vez que se quiera probar:
 *   docker compose exec -e DB_DATABASE=hotellink_concurrencia back php tests/Concurrencia/ejecutar.php
 *
 * Devuelve 0 si todos los escenarios pasan y 1 si alguno falla.
 */

use App\Models\Habitacion;
use App\Models\Huesped;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

require __DIR__ . '/base_segura.php';
exigirBaseDeConcurrencia();

//Tiempo para que todos los procesos terminen de cargar antes de salir juntos
const VENTAJA_SEGUNDOS = 6;

Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

$huesped = Huesped::create(['nombres' => 'Prueba', 'apellidos' => 'Concurrencia'])->id;
$fallos = 0;

//Lanza un proceso por pedido, todos con la misma hora de salida, y devuelve sus resultados
function competir(array $pedidos): array
{
    $inicio = microtime(true) + VENTAJA_SEGUNDOS;
    $procesos = [];

    foreach ($pedidos as $datos) {
        $orden = [PHP_BINARY, __DIR__ . '/reservar.php', json_encode(['inicio' => $inicio, 'datos' => $datos])];
        $proceso = proc_open($orden, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias);
        $procesos[] = [$proceso, $tuberias];
    }

    $resultados = [];
    foreach ($procesos as [$proceso, $tuberias]) {
        $salida = stream_get_contents($tuberias[1]);
        $errores = stream_get_contents($tuberias[2]);
        proc_close($proceso);
        $resultados[] = json_decode($salida, true) ?? ['resultado' => 'error', 'mensaje' => substr($salida . $errores, 0, 200)];
    }

    return $resultados;
}

function contar(array $resultados): array
{
    $conteo = ['creada' => 0, 'rechazada' => 0, 'error' => 0];
    foreach ($resultados as $r) {
        $conteo[$r['resultado']]++;
    }

    return $conteo;
}

function pedido(int $habitacion, string $llegada, string $salida, int $huesped): array
{
    return [
        'habitacion_id' => $habitacion,
        'huesped_id' => $huesped,
        'fecha_llegada' => $llegada,
        'fecha_salida' => $salida,
        'adultos' => 1,
        'canal' => 'web',
    ];
}

function informar(string $nombre, bool $paso, array $conteo, array $resultados, int &$fallos): void
{
    $fallos += $paso ? 0 : 1;
    printf("%-6s %-55s creadas=%d rechazadas=%d errores=%d\n", $paso ? 'OK' : 'FALLO', $nombre, $conteo['creada'], $conteo['rechazada'], $conteo['error']);

    foreach ($resultados as $r) {
        if ($r['resultado'] === 'error') {
            echo "       error: {$r['mensaje']}\n";
        }
    }
}

//Toda habitacion se puede reservar en estos escenarios: ninguna en mantenimiento
$porTipo = Habitacion::where('estado', 1)->where('estado_habitacion', '!=', Habitacion::MANTENIMIENTO)->orderBy('id')->get()->groupBy('tipo_habitacion_id');
$habitacion = $porTipo->first()->first()->id;
$dosDelTipo = $porTipo->first(fn ($h) => $h->count() >= 2);
$todas = $porTipo->flatten();

//Cada escenario usa fechas propias para no chocar con los anteriores
$fecha = Carbon::today()->addYears(3);
$tramo = function (int $noches) use (&$fecha) {
    $llegada = $fecha->toDateString();
    $salida = $fecha->copy()->addDays($noches)->toDateString();
    $fecha = $fecha->copy()->addDays($noches + 5);

    return [$llegada, $salida];
};

$rondas = ['creada' => 0, 'rechazada' => 0, 'error' => 0];
$ganadorUnico = true;
$acumulado = [];
for ($i = 0; $i < 10; $i++) {
    [$llegada, $salida] = $tramo(2);
    $resultados = competir(array_fill(0, 2, pedido($habitacion, $llegada, $salida, $huesped)));
    $conteo = contar($resultados);
    $ganadorUnico = $ganadorUnico && $conteo['creada'] === 1 && $conteo['rechazada'] === 1;
    foreach ($conteo as $clave => $valor) {
        $rondas[$clave] += $valor;
    }
    $acumulado = array_merge($acumulado, $resultados);
}
informar('Dos solicitudes por la misma habitacion, 10 rondas', $ganadorUnico, $rondas, $acumulado, $fallos);

[$llegada, $salida] = $tramo(3);
$resultados = competir(array_fill(0, 20, pedido($habitacion, $llegada, $salida, $huesped)));
$conteo = contar($resultados);
informar('20 solicitudes por la misma habitacion', $conteo['creada'] === 1 && $conteo['rechazada'] === 19, $conteo, $resultados, $fallos);

[$llegada] = $tramo(4);
$medio = Carbon::parse($llegada)->addDays(2)->toDateString();
$fin = Carbon::parse($llegada)->addDays(4)->toDateString();
$resultados = competir([pedido($habitacion, $llegada, $medio, $huesped), pedido($habitacion, $medio, $fin, $huesped)]);
$conteo = contar($resultados);
informar('Salida y entrada el mismo dia, en paralelo', $conteo['creada'] === 2, $conteo, $resultados, $fallos);

//a y c caben juntas; b choca con ambas. Cualquier resultado sin traslape es valido
[$llegada] = $tramo(7);
$dia = fn (int $n) => Carbon::parse($llegada)->addDays($n)->toDateString();
$resultados = competir([
    pedido($habitacion, $dia(0), $dia(3), $huesped),
    pedido($habitacion, $dia(2), $dia(5), $huesped),
    pedido($habitacion, $dia(4), $dia(7), $huesped),
]);
$conteo = contar($resultados);
informar('Traslapes parciales en cadena', $conteo['error'] === 0 && $conteo['creada'] >= 1, $conteo, $resultados, $fallos);

//Dos habitaciones del mismo tipo: cada pedido debe conseguir la suya sin esperar en vano
[$llegada, $salida] = $tramo(2);
$dos = $dosDelTipo->take(2)->pluck('id');
$resultados = competir([pedido($dos[0], $llegada, $salida, $huesped), pedido($dos[1], $llegada, $salida, $huesped)]);
$conteo = contar($resultados);
informar('Dos habitaciones distintas del mismo tipo', $conteo['creada'] === 2, $conteo, $resultados, $fallos);

//Interbloqueos: todas las habitaciones del hotel a la vez, varias rondas
$lleno = ['creada' => 0, 'rechazada' => 0, 'error' => 0];
$acumulado = [];
for ($i = 0; $i < 5; $i++) {
    [$llegada, $salida] = $tramo(2);
    $resultados = competir($todas->map(fn ($h) => pedido($h->id, $llegada, $salida, $huesped))->all());
    foreach (contar($resultados) as $clave => $valor) {
        $lleno[$clave] += $valor;
    }
    $acumulado = array_merge($acumulado, $resultados);
}
informar('Todas las habitaciones a la vez, 5 rondas (' . $todas->count() . ' por ronda)', $lleno['creada'] === 5 * $todas->count(), $lleno, $acumulado, $fallos);

//La prueba definitiva, sea cual sea el orden en que ganaron: nada traslapado en la base
$traslapes = DB::select('
    SELECT a.codigo AS a, b.codigo AS b FROM reservas a
    JOIN reservas b ON a.habitacion_id = b.habitacion_id AND a.id < b.id
        AND a.fecha_llegada < b.fecha_salida AND b.fecha_llegada < a.fecha_salida
    WHERE a.estado_reserva <> 5 AND b.estado_reserva <> 5
');
$sinNoches = DB::select('
    SELECT r.codigo FROM reservas r
    LEFT JOIN reserva_noches n ON n.reserva_id = r.id
    WHERE r.huesped_id = ?
    GROUP BY r.id, r.codigo, r.fecha_salida, r.fecha_llegada
    HAVING COUNT(n.id) <> DATEDIFF(r.fecha_salida, r.fecha_llegada)
', [$huesped]);
$codigos = DB::table('reservas')->count() === DB::table('reservas')->distinct()->count('codigo');

$vacio = ['creada' => 0, 'rechazada' => 0, 'error' => 0];
informar('La base no tiene ningun par de reservas traslapadas', $traslapes === [], $vacio, [], $fallos);
informar('Cada reserva tiene una fila por noche', $sinNoches === [], $vacio, [], $fallos);
informar('Los codigos de confirmacion no se repiten', $codigos, $vacio, [], $fallos);

echo $fallos === 0 ? "\nTODO CORRECTO\n" : "\n$fallos ESCENARIO(S) FALLARON\n";
exit($fallos === 0 ? 0 : 1);
