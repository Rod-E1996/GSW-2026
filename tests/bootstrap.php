<?php

/**
 * Prepara la base de datos exclusiva de las pruebas antes de ejecutarlas.
 * Las pruebas nunca usan la base de desarrollo: phpunit.xml fuerza SQLite y
 * este archivo se detiene si detecta otra configuracion.
 */

use App\Models\TipoHabitacionImagen;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/../vendor/autoload.php';

chdir(dirname(__DIR__));

$base = 'storage/framework/testing/pruebas.sqlite';

if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $base) {
    fwrite(STDERR, "Pruebas canceladas: phpunit.xml no apunta a la base de pruebas.\n");
    exit(1);
}

if (is_file($base)) {
    unlink($base);
}
touch($base);

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

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
