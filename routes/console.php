<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

//Respaldo de la base; lo ejecuta a diario el programador de tareas (app/Console/Kernel.php)
Artisan::command('respaldos:generar', function () {
    $respaldo = app(\App\Services\RespaldoService::class)->generar(null, \App\Models\Respaldo::PROGRAMADO);
    $this->info('Respaldo generado: ' . $respaldo->archivo);
})->purpose('Genera un respaldo completo de la base de datos');
