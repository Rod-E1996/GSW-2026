<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TipoHabitacionController;
use App\Http\Controllers\Api\Admin\TipoHabitacionController as AdminTipoHabitacionController;
use App\Http\Controllers\Api\Admin\AuditarController as AdminAuditarController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas del API REST que consume el portal React.
| El prefijo /api lo agrega el RouteServiceProvider.
|
*/

// ===== Autenticacion =====
Route::post('/login', [AuthController::class, 'login']);

// Recuperacion de contrasena (publicas, con limite de intentos)
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:6,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ===== Portal publico (sin autenticacion) =====
Route::get('/tipos-habitacion', [TipoHabitacionController::class, 'index']);

// ===== Panel de administracion (token + permisos) =====
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // Tipos de habitacion
    Route::get('/tipos-habitacion', [AdminTipoHabitacionController::class, 'index'])
        ->middleware('permission:tipo_habitacion_index,sanctum');
    Route::post('/tipos-habitacion', [AdminTipoHabitacionController::class, 'store'])
        ->middleware('permission:tipo_habitacion_store,sanctum');
    Route::get('/tipos-habitacion/{id}', [AdminTipoHabitacionController::class, 'show'])
        ->middleware('permission:tipo_habitacion_show,sanctum');
    Route::put('/tipos-habitacion/{id}', [AdminTipoHabitacionController::class, 'update'])
        ->middleware('permission:tipo_habitacion_update,sanctum');
    Route::delete('/tipos-habitacion/{id}', [AdminTipoHabitacionController::class, 'destroy'])
        ->middleware('permission:tipo_habitacion_destroy,sanctum');

    // Imagenes del tipo de habitacion (usan el permiso de edicion)
    Route::post('/tipos-habitacion/{id}/imagenes', [AdminTipoHabitacionController::class, 'imagenesStore'])
        ->middleware('permission:tipo_habitacion_update,sanctum');
    Route::delete('/tipos-habitacion/{id}/imagenes/{imagen}', [AdminTipoHabitacionController::class, 'imagenDestroy'])
        ->middleware('permission:tipo_habitacion_update,sanctum');
    Route::put('/tipos-habitacion/{id}/imagenes/{imagen}/principal', [AdminTipoHabitacionController::class, 'imagenPrincipal'])
        ->middleware('permission:tipo_habitacion_update,sanctum');

    // Auditoria (solo lectura)
    Route::get('/auditoria', [AdminAuditarController::class, 'index'])
        ->middleware('permission:auditar_index,sanctum');
    Route::get('/auditoria/{id}', [AdminAuditarController::class, 'show'])
        ->middleware('permission:auditar_show,sanctum');
});
