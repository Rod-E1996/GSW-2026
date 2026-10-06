<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TipoHabitacionController;
use App\Http\Controllers\Api\Admin\TipoHabitacionController as AdminTipoHabitacionController;
use App\Http\Controllers\Api\Admin\AuditarController as AdminAuditarController;
use App\Http\Controllers\Api\Admin\ErrorLogController as AdminErrorLogController;
use App\Http\Controllers\Api\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\Admin\PermisoController as AdminPermisoController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\SesionController as AdminSesionController;

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
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');

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

    // Roles (CRUD + asignacion de permisos)
    Route::get('/roles', [AdminRoleController::class, 'index'])
        ->middleware('permission:role_index,sanctum');
    Route::post('/roles', [AdminRoleController::class, 'store'])
        ->middleware('permission:role_store,sanctum');
    Route::get('/roles/{id}', [AdminRoleController::class, 'show'])
        ->middleware('permission:role_show,sanctum');
    Route::put('/roles/{id}', [AdminRoleController::class, 'update'])
        ->middleware('permission:role_update,sanctum');
    Route::delete('/roles/{id}', [AdminRoleController::class, 'destroy'])
        ->middleware('permission:role_destroy,sanctum');
    Route::put('/roles/{id}/permisos', [AdminRoleController::class, 'permisos'])
        ->middleware('permission:role_move_permiso,sanctum');

    // Usuarios (CRUD + estado + aviso de login). /roles antes de /{id}.
    Route::get('/usuarios', [AdminUserController::class, 'index'])
        ->middleware('permission:usuario_index,sanctum');
    Route::get('/usuarios/roles', [AdminUserController::class, 'rolesDisponibles'])
        ->middleware('permission:usuario_index,sanctum');
    Route::post('/usuarios', [AdminUserController::class, 'store'])
        ->middleware('permission:usuario_store,sanctum');
    Route::get('/usuarios/{id}', [AdminUserController::class, 'show'])
        ->middleware('permission:usuario_show,sanctum');
    Route::put('/usuarios/{id}', [AdminUserController::class, 'update'])
        ->middleware('permission:usuario_update,sanctum');
    Route::put('/usuarios/{id}/estado', [AdminUserController::class, 'estado'])
        ->middleware('permission:usuario_estado,sanctum');
    Route::put('/usuarios/{id}/login-notificacion', [AdminUserController::class, 'loginNotificacion'])
        ->middleware('permission:usuario_login_notificacion,sanctum');

    // Sesiones por usuario (tokens)
    Route::get('/usuarios/{id}/sesiones', [AdminSesionController::class, 'deUsuario'])
        ->middleware('permission:usuario_sessiones,sanctum');
    Route::delete('/usuarios/{id}/sesiones/{token}', [AdminSesionController::class, 'cerrarDeUsuario'])
        ->middleware('permission:usuario_cerrar_session,sanctum');
    Route::delete('/usuarios/{id}/sesiones', [AdminSesionController::class, 'cerrarTodasDeUsuario'])
        ->middleware('permission:usuario_cerrar_todas_sessiones,sanctum');

    // Sesiones globales del sistema (tokens)
    Route::get('/sesiones', [AdminSesionController::class, 'index'])
        ->middleware('permission:session_index,sanctum');
    Route::delete('/sesiones/{token}', [AdminSesionController::class, 'cerrar'])
        ->middleware('permission:session_cerrar,sanctum');

    // Permisos (catalogo: listar, crear, eliminar)
    Route::get('/permisos', [AdminPermisoController::class, 'index'])
        ->middleware('permission:permiso_index,sanctum');
    Route::post('/permisos', [AdminPermisoController::class, 'store'])
        ->middleware('permission:permiso_move,sanctum');
    Route::delete('/permisos/{id}', [AdminPermisoController::class, 'destroy'])
        ->middleware('permission:permiso_move,sanctum');

    // Error logs (ver, resolver y generar registro de prueba)
    Route::get('/error-logs', [AdminErrorLogController::class, 'index'])
        ->middleware('permission:error_log_index,sanctum');
    Route::post('/error-logs/prueba', [AdminErrorLogController::class, 'prueba'])
        ->middleware('permission:error_log_create,sanctum');
    Route::get('/error-logs/{id}', [AdminErrorLogController::class, 'show'])
        ->middleware('permission:error_log_show,sanctum');
    Route::put('/error-logs/{id}/estado', [AdminErrorLogController::class, 'estado'])
        ->middleware('permission:error_log_estado,sanctum');
});
