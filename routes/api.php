<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DisponibilidadController;
use App\Http\Controllers\Api\ReservaController;
use App\Http\Controllers\Api\MiCuentaController;
use App\Http\Controllers\Api\ServicioController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\TipoHabitacionController;
use App\Http\Controllers\Api\Admin\TipoHabitacionController as AdminTipoHabitacionController;
use App\Http\Controllers\Api\Admin\AuditarController as AdminAuditarController;
use App\Http\Controllers\Api\Admin\ErrorLogController as AdminErrorLogController;
use App\Http\Controllers\Api\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\Admin\PermisoController as AdminPermisoController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\SesionController as AdminSesionController;
use App\Http\Controllers\Api\Admin\ConfiguracionController as AdminConfiguracionController;
use App\Http\Controllers\Api\Admin\HabitacionController as AdminHabitacionController;
use App\Http\Controllers\Api\Admin\BloqueoHabitacionController as AdminBloqueoController;
use App\Http\Controllers\Api\Admin\TemporadaController as AdminTemporadaController;
use App\Http\Controllers\Api\Admin\TarifaController as AdminTarifaController;
use App\Http\Controllers\Api\Admin\CategoriaServicioController as AdminCategoriaServicioController;
use App\Http\Controllers\Api\Admin\ServicioController as AdminServicioController;

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
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');

// Recuperacion de contrasena (publicas, con limite de intentos)
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:6,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:6,1');

// Verificacion de correo: el enlace firmado basta, no hace falta sesion
Route::get('/correo/verificar/{id}/{hash}', [AuthController::class, 'verificarCorreo'])
    ->middleware('throttle:6,1')
    ->name('api.correo.verificar');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/correo/reenviar', [AuthController::class, 'reenviarVerificacion'])->middleware('throttle:3,1');
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ===== Perfil del usuario autenticado =====
Route::middleware('auth:sanctum')->prefix('perfil')->group(function () {
    Route::get('/', [PerfilController::class, 'show'])
        ->middleware('permission:perfil_show,sanctum');
    // Con limite de intentos: piden la contrasena actual
    Route::put('/', [PerfilController::class, 'update'])
        ->middleware(['permission:perfil_edit,sanctum', 'throttle:10,1']);
    Route::put('/password', [PerfilController::class, 'password'])
        ->middleware(['permission:perfil_edit_password,sanctum', 'throttle:6,1']);
    Route::delete('/sesiones/{token}', [PerfilController::class, 'cerrarSesion'])
        ->middleware('permission:perfil_cerrar_session,sanctum');
});

// ===== Portal publico (sin autenticacion) =====
Route::get('/tipos-habitacion', [TipoHabitacionController::class, 'index']);
Route::get('/disponibilidad', [DisponibilidadController::class, 'index']);
Route::get('/servicios', [ServicioController::class, 'index']);
Route::post('/reservas/cotizar', [ReservaController::class, 'cotizar'])->middleware('throttle:60,1');

// ===== Reserva en linea =====
Route::post('/reservas', [ReservaController::class, 'store'])
    ->middleware(['auth:sanctum', 'permission:reserva_solicitar,sanctum', 'correo.verificado', 'throttle:10,1']);

// ===== Mi cuenta (solo las reservas propias) =====
Route::middleware('auth:sanctum')->prefix('mi-cuenta')->group(function () {
    Route::get('/huesped', [MiCuentaController::class, 'huesped']);
    Route::get('/reservas', [MiCuentaController::class, 'reservas']);
    Route::get('/reservas/{codigo}', [MiCuentaController::class, 'reserva']);
    Route::post('/reservas/{codigo}/cancelar', [MiCuentaController::class, 'cancelar'])->middleware('throttle:10,1');
});

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

    // Habitaciones. /catalogos antes de /{id}.
    Route::get('/habitaciones', [AdminHabitacionController::class, 'index'])
        ->middleware('permission:habitacion_index,sanctum');
    Route::get('/habitaciones/catalogos', [AdminHabitacionController::class, 'catalogos'])
        ->middleware('permission:habitacion_index,sanctum');
    Route::post('/habitaciones', [AdminHabitacionController::class, 'store'])
        ->middleware('permission:habitacion_store,sanctum');
    Route::get('/habitaciones/{id}', [AdminHabitacionController::class, 'show'])
        ->middleware('permission:habitacion_show,sanctum');
    Route::put('/habitaciones/{id}', [AdminHabitacionController::class, 'update'])
        ->middleware('permission:habitacion_update,sanctum');
    Route::put('/habitaciones/{id}/estado', [AdminHabitacionController::class, 'estado'])
        ->middleware('permission:habitacion_estado,sanctum');
    Route::delete('/habitaciones/{id}', [AdminHabitacionController::class, 'destroy'])
        ->middleware('permission:habitacion_destroy,sanctum');

    // Bloqueos de habitacion
    Route::get('/bloqueos', [AdminBloqueoController::class, 'index'])
        ->middleware('permission:bloqueo_index,sanctum');
    Route::post('/bloqueos', [AdminBloqueoController::class, 'store'])
        ->middleware('permission:bloqueo_store,sanctum');
    Route::put('/bloqueos/{id}', [AdminBloqueoController::class, 'update'])
        ->middleware('permission:bloqueo_update,sanctum');
    Route::delete('/bloqueos/{id}', [AdminBloqueoController::class, 'destroy'])
        ->middleware('permission:bloqueo_destroy,sanctum');

    // Temporadas
    Route::get('/temporadas', [AdminTemporadaController::class, 'index'])
        ->middleware('permission:temporada_index,sanctum');
    Route::post('/temporadas', [AdminTemporadaController::class, 'store'])
        ->middleware('permission:temporada_store,sanctum');
    Route::get('/temporadas/{id}', [AdminTemporadaController::class, 'show'])
        ->middleware('permission:temporada_index,sanctum');
    Route::put('/temporadas/{id}', [AdminTemporadaController::class, 'update'])
        ->middleware('permission:temporada_update,sanctum');
    Route::delete('/temporadas/{id}', [AdminTemporadaController::class, 'destroy'])
        ->middleware('permission:temporada_destroy,sanctum');

    // Tarifas (matriz por tipo y temporada)
    Route::get('/tarifas', [AdminTarifaController::class, 'index'])
        ->middleware('permission:tarifa_index,sanctum');
    Route::put('/tarifas', [AdminTarifaController::class, 'update'])
        ->middleware('permission:tarifa_update,sanctum');

    // Categorias de servicio
    Route::get('/categorias-servicio', [AdminCategoriaServicioController::class, 'index'])
        ->middleware('permission:categoria_servicio_index,sanctum');
    Route::post('/categorias-servicio', [AdminCategoriaServicioController::class, 'store'])
        ->middleware('permission:categoria_servicio_store,sanctum');
    Route::get('/categorias-servicio/{id}', [AdminCategoriaServicioController::class, 'show'])
        ->middleware('permission:categoria_servicio_index,sanctum');
    Route::put('/categorias-servicio/{id}', [AdminCategoriaServicioController::class, 'update'])
        ->middleware('permission:categoria_servicio_update,sanctum');
    Route::delete('/categorias-servicio/{id}', [AdminCategoriaServicioController::class, 'destroy'])
        ->middleware('permission:categoria_servicio_destroy,sanctum');

    // Servicios complementarios
    Route::get('/servicios', [AdminServicioController::class, 'index'])
        ->middleware('permission:servicio_index,sanctum');
    Route::post('/servicios', [AdminServicioController::class, 'store'])
        ->middleware('permission:servicio_store,sanctum');
    Route::get('/servicios/{id}', [AdminServicioController::class, 'show'])
        ->middleware('permission:servicio_index,sanctum');
    Route::put('/servicios/{id}', [AdminServicioController::class, 'update'])
        ->middleware('permission:servicio_update,sanctum');
    Route::delete('/servicios/{id}', [AdminServicioController::class, 'destroy'])
        ->middleware('permission:servicio_destroy,sanctum');
    Route::post('/servicios/{id}/imagen', [AdminServicioController::class, 'imagenStore'])
        ->middleware('permission:servicio_update,sanctum');
    Route::delete('/servicios/{id}/imagen', [AdminServicioController::class, 'imagenDestroy'])
        ->middleware('permission:servicio_update,sanctum');

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

    // Parametros del sistema
    Route::get('/configuraciones', [AdminConfiguracionController::class, 'index'])
        ->middleware('permission:configuracion_index,sanctum');
    Route::put('/configuraciones', [AdminConfiguracionController::class, 'update'])
        ->middleware('permission:configuracion_update,sanctum');

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
