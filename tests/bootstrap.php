<?php

/**
 * Prepara la base de datos exclusiva de las pruebas antes de ejecutarlas.
 * Las pruebas nunca usan la base de desarrollo: se impone la configuracion de
 * phpunit.xml y este archivo se detiene antes de migrar si la conexion real es otra.
 */

use App\Models\TipoHabitacionImagen;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/../vendor/autoload.php';

chdir(dirname(__DIR__));

$base = 'storage/framework/testing/pruebas.sqlite';

// PHPUnit solo cambia $_ENV y putenv, pero Laravel lee primero $_SERVER, donde siguen
// las variables reales del contenedor o del sistema.
foreach ($_ENV as $variable => $valor) {
    $_SERVER[$variable] = $valor;
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Se comprueba lo que Laravel va a usar de verdad, no las variables de entorno.
$conexion = config('database.default');
$destino = config("database.connections.$conexion.database");

if ($conexion !== 'sqlite' || $destino !== $base) {
    fwrite(STDERR, "Pruebas canceladas: apuntan a \"$conexion: $destino\", no a la base de pruebas.\n");
    exit(1);
}

if (is_file($base)) {
    unlink($base);
}
touch($base);

// Los seeders guardan fotos: se desvian a un disco de prueba para no tocar storage/app/public.
Storage::fake(TipoHabitacionImagen::DISCO);

if (Artisan::call('migrate', ['--seed' => true, '--force' => true]) !== 0) {
    fwrite(STDERR, Artisan::output());
    exit(1);
}

// Cada prueba arranca su propia aplicacion: se dejan limpios los manejadores de errores de esta.
$app->flush();
restore_error_handler();
restore_exception_handler();
