<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TipoHabitacionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas del API REST que consume el portal React.
| El prefijo /api lo agrega el RouteServiceProvider.
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ===== Portal publico (sin autenticacion) =====
Route::get('/tipos-habitacion', [TipoHabitacionController::class, 'index']);
