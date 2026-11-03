<?php

/**
 * Un proceso de la prueba de concurrencia: espera la hora acordada, intenta
 * reservar e imprime el resultado. Lo lanza tests/Concurrencia/ejecutar.php.
 */

use App\Exceptions\HabitacionNoDisponible;
use App\Services\ReservaService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

require __DIR__ . '/base_segura.php';
exigirBaseDeConcurrencia();

$pedido = json_decode($argv[1], true);
$servicio = app(ReservaService::class);

//Todos los procesos salen juntos, una vez que Laravel ya cargo en cada uno
if ($pedido['inicio'] > microtime(true)) {
    time_sleep_until($pedido['inicio']);
}

try {
    $reserva = $servicio->crear($pedido['datos']);
    echo json_encode(['resultado' => 'creada', 'codigo' => $reserva->codigo]);
} catch (HabitacionNoDisponible $e) {
    echo json_encode(['resultado' => 'rechazada']);
} catch (Throwable $e) {
    echo json_encode(['resultado' => 'error', 'mensaje' => substr($e->getMessage(), 0, 200)]);
}
