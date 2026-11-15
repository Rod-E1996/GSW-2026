<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| El sitio publico, el registro, el inicio de sesion y el panel de administracion
| estan en el portal React (frontend/) y usan la API (routes/api.php). El panel
| Blade heredado ya no tiene rutas: sus controladores y vistas quedan sin uso.
|
*/

//Quien entra directo a Laravel llega al portal
Route::get('/', fn () => redirect()->away(config('app.frontend_url')))->name('home');
