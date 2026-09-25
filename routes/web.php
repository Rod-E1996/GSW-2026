<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

// INICIO DE LAS RUTAS PUBLICAS
// =============================================================================================================================================

Route::get('/', [App\Http\Controllers\PublicController::class, 'home'])->name('home');

// =============================================================================================================================================
// FIN DE LAS RUTAS PUBLICAS



// RUTAS PARA LOS ROLES (Super Administrador, Administrador, Recepcionista y Huésped)
// =============================================================================================================================================

//Ruta despues de loguearse
Route::get('/afterlogin', [App\Http\Controllers\HomeController::class, 'afterlogin'])->name('afterlogin');
//Route::get('/afterlogin', [App\Http\Controllers\HomeController::class, 'afterlogin'])->name('afterlogin')->middleware(['auth', 'role:Administrador|Huésped']); //Para dar accesos por roles

// =============================================================================================================================================
// FIN RUTAS PARA LOS ROLES (Super Administrador, Administrador, Recepcionista y Huésped)



// RUTAS SEGUN PERMISOS
// =============================================================================================================================================

//Permisos
Route::get('/permiso', [App\Http\Controllers\PermisosController::class, 'index'])->name('permiso_index')->middleware(['auth', 'permission:permiso_index']);
Route::post('/move', [App\Http\Controllers\PermisosController::class, 'move'])->name('permiso_move')->middleware(['auth', 'permission:permiso_move']);

//Roles
Route::prefix("/role")->group(function(){
    Route::get('/', [App\Http\Controllers\RolesController::class, 'index'])->name('role_index')->middleware(['auth', 'permission:role_index']);
    Route::get('/create', [App\Http\Controllers\RolesController::class, 'create'])->name('role_create')->middleware(['auth', 'permission:role_create']);
    Route::post('/', [App\Http\Controllers\RolesController::class, 'store'])->name('role_store')->middleware(['auth', 'permission:role_store']);
    Route::get('/{id}', [App\Http\Controllers\RolesController::class, 'show'])->name('role_show')->middleware(['auth', 'permission:role_show']);
    Route::get('/{id}/edit', [App\Http\Controllers\RolesController::class, 'edit'])->name('role_edit')->middleware(['auth', 'permission:role_edit']);
    Route::put('/{id}', [App\Http\Controllers\RolesController::class, 'update'])->name('role_update')->middleware(['auth', 'permission:role_update']);
    Route::post('/{id}', [App\Http\Controllers\RolesController::class, 'destroy'])->name('role_destroy')->middleware(['auth', 'permission:role_destroy']);
});

Route::post('/movePermisos', [App\Http\Controllers\RolesController::class, 'movePermisos'])->name('role_move_permiso')->middleware(['auth', 'permission:role_move_permiso']);

//Usuarios
Route::prefix("/usuario")->group(function(){
    Route::get('/', [App\Http\Controllers\UsuariosController::class, 'index'])->name('usuario_index')->middleware(['auth', 'permission:usuario_index']);                              //Para el index de usuario

    //Sesiones de todo el sistema (solo Super Administrador). Van antes de "/{id}" para que esa ruta no las capture
    Route::get('/sessiones', [App\Http\Controllers\SessionesController::class, 'index'])->name('session_index')->middleware(['auth', 'permission:session_index']);                                   //Para ver las sesiones abiertas de todos los usuarios
    Route::post('/sessiones/cerrar/{session_id}', [App\Http\Controllers\SessionesController::class, 'cerrar'])->name('session_cerrar')->middleware(['auth', 'permission:session_cerrar']);            //Para cerrar una sesion puntual desde la pantalla global

    //Sesiones de un usuario puntual (solo Super Administrador)
    Route::get('/{id}/sessiones', [App\Http\Controllers\UsuariosController::class, 'sessiones'])->name('usuario_sessiones')->middleware(['auth', 'permission:usuario_sessiones']);                                                    //Para ver las sesiones abiertas de un usuario
    Route::post('/{id}/sessiones/cerrar/{session_id}', [App\Http\Controllers\UsuariosController::class, 'cerrarSession'])->name('usuario_cerrar_session')->middleware(['auth', 'permission:usuario_cerrar_session']);                  //Para cerrar una sesion puntual de un usuario
    Route::post('/{id}/sessiones/cerrar-todas', [App\Http\Controllers\UsuariosController::class, 'cerrarTodasSessiones'])->name('usuario_cerrar_todas_sessiones')->middleware(['auth', 'permission:usuario_cerrar_todas_sessiones']); //Para cerrar todas las sesiones de un usuario
    Route::get('/create', [App\Http\Controllers\UsuariosController::class, 'create'])->name('usuario_create')->middleware(['auth', 'permission:usuario_create']);                     //Para el create de usuario
    Route::post('/', [App\Http\Controllers\UsuariosController::class, 'store'])->name('usuario_store')->middleware(['auth', 'permission:usuario_store']);                             //Para guardar la data del create de usuario
    Route::get('/{id}', [App\Http\Controllers\UsuariosController::class, 'show'])->name('usuario_show')->middleware(['auth', 'permission:usuario_show']);                             //Para el show de usuario
    Route::get('/{id}/edit', [App\Http\Controllers\UsuariosController::class, 'edit'])->name('usuario_edit')->middleware(['auth', 'permission:usuario_edit']);                        //Para el edit de usuario
    Route::put('/{id}', [App\Http\Controllers\UsuariosController::class, 'update'])->name('usuario_update')->middleware(['auth', 'permission:usuario_update']);                       //Para guardar la data del edit de usuario
    Route::post('/estado/{id}', [App\Http\Controllers\UsuariosController::class, 'estado'])->name('usuario_estado')->middleware(['auth', 'permission:usuario_estado']);               //Para el estado del usuario
    Route::post('/login_notificacion/{id}', [App\Http\Controllers\UsuariosController::class, 'estadoNotificacionLogin'])->name('usuario_login_notificacion')->middleware(['auth', 'permission:usuario_login_notificacion']);               //Para el estado de notificaciones de inicio de sesion
});

//Dashboard
Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'dashboard'])->name('dashboard')->middleware(['auth', 'permission:dashboard']);

//Perfil
Route::prefix("/perfil")->group(function(){
    Route::get('/', [App\Http\Controllers\PerfilController::class, 'show'])->name('perfil_show')->middleware(['auth', 'permission:perfil_show']);
    Route::post('/editar', [App\Http\Controllers\PerfilController::class, 'edit'])->name('perfil_edit')->middleware(['auth', 'permission:perfil_edit']);
    Route::post('/editar-pass', [App\Http\Controllers\PerfilController::class, 'editar_contrasena'])->name('perfil_edit_password')->middleware(['auth', 'permission:perfil_edit_password']);
    Route::post('/cerrar-session', [App\Http\Controllers\PerfilController::class, 'cerrarSession'])->name('perfil_cerrar_session')->middleware(['auth', 'permission:perfil_cerrar_session']);
});

//Auditoria
Route::get('/auditar', [App\Http\Controllers\AuditarController::class, 'index'])->name('auditar_index')->middleware(['auth', 'permission:auditar_index']);
Route::get('/auditar/{id}', [App\Http\Controllers\AuditarController::class, 'show'])->name('auditar_show')->middleware(['auth', 'permission:auditar_show']);

// Error log
Route::prefix("/error_log")->group(function(){
    Route::get('/', [App\Http\Controllers\ErrorLogsController::class, 'index'])->name('error_log_index')->middleware(['auth', 'permission:error_log_index']);                             //Para el index de error logs
    Route::get('/{id}', [App\Http\Controllers\ErrorLogsController::class, 'show'])->name('error_log_show')->middleware(['auth', 'permission:error_log_show']);                            //Para el show de error logs
    Route::get('/crearError/{id}', [App\Http\Controllers\ErrorLogsController::class, 'crearError'])->name('error_log_create')->middleware(['auth', 'permission:error_log_create']);      //Para crear error logs
    Route::get('/estado/{id}', [App\Http\Controllers\ErrorLogsController::class, 'estado'])->name('error_log_estado')->middleware(['auth', 'permission:error_log_estado']);              //Para cambiar el estado del error log
});

//Ejemplos
Route::prefix("/ejemplo")->group(function(){
    Route::get('/', [App\Http\Controllers\EjemplosController::class, 'index'])->name('ejemplo_index')->middleware(['auth', 'permission:ejemplo_index']);                              //Para el index de ejemplo
    Route::get('/create', [App\Http\Controllers\EjemplosController::class, 'create'])->name('ejemplo_create')->middleware(['auth', 'permission:ejemplo_create']);                     //Para el create de ejemplo
    Route::post('/', [App\Http\Controllers\EjemplosController::class, 'store'])->name('ejemplo_store')->middleware(['auth', 'permission:ejemplo_store']);                             //Para guardar la data del create de ejemplo
    Route::get('/{id}', [App\Http\Controllers\EjemplosController::class, 'show'])->name('ejemplo_show')->middleware(['auth', 'permission:ejemplo_show']);                             //Para el show de ejemplo
    Route::get('/{id}/edit', [App\Http\Controllers\EjemplosController::class, 'edit'])->name('ejemplo_edit')->middleware(['auth', 'permission:ejemplo_edit']);                        //Para el edit de ejemplo
    Route::put('/{id}', [App\Http\Controllers\EjemplosController::class, 'update'])->name('ejemplo_update')->middleware(['auth', 'permission:ejemplo_update']);                       //Para guardar la data del edit de ejemplo
    Route::post('/{id}', [App\Http\Controllers\EjemplosController::class, 'destroy'])->name('ejemplo_destroy')->middleware(['auth', 'permission:ejemplo_destroy']);                   //Para el eliminar de ejemplo (eliminado logico)
});

//Tipos de habitación
Route::prefix("/tipo_habitacion")->group(function(){
    Route::get('/', [App\Http\Controllers\TiposHabitacionController::class, 'index'])->name('tipo_habitacion_index')->middleware(['auth', 'permission:tipo_habitacion_index']);                      //Para el index de tipos de habitacion
    Route::get('/create', [App\Http\Controllers\TiposHabitacionController::class, 'create'])->name('tipo_habitacion_create')->middleware(['auth', 'permission:tipo_habitacion_create']);             //Para el create de tipos de habitacion
    Route::post('/', [App\Http\Controllers\TiposHabitacionController::class, 'store'])->name('tipo_habitacion_store')->middleware(['auth', 'permission:tipo_habitacion_store']);                     //Para guardar la data del create
    Route::get('/{id}', [App\Http\Controllers\TiposHabitacionController::class, 'show'])->name('tipo_habitacion_show')->middleware(['auth', 'permission:tipo_habitacion_show']);                     //Para el show de tipos de habitacion
    Route::get('/{id}/edit', [App\Http\Controllers\TiposHabitacionController::class, 'edit'])->name('tipo_habitacion_edit')->middleware(['auth', 'permission:tipo_habitacion_edit']);                //Para el edit de tipos de habitacion
    Route::put('/{id}', [App\Http\Controllers\TiposHabitacionController::class, 'update'])->name('tipo_habitacion_update')->middleware(['auth', 'permission:tipo_habitacion_update']);               //Para guardar la data del edit
    Route::post('/{id}', [App\Http\Controllers\TiposHabitacionController::class, 'destroy'])->name('tipo_habitacion_destroy')->middleware(['auth', 'permission:tipo_habitacion_destroy']);           //Para el eliminar (eliminado logico)

    //Fotos del tipo de habitacion (forman parte de la edicion, por eso usan el permiso tipo_habitacion_update)
    Route::post('/{id}/imagen/{imagen_id}/eliminar', [App\Http\Controllers\TiposHabitacionController::class, 'imagenDestroy'])->name('tipo_habitacion_imagen_destroy')->middleware(['auth', 'permission:tipo_habitacion_update']);     //Para eliminar una foto
    Route::post('/{id}/imagen/{imagen_id}/principal', [App\Http\Controllers\TiposHabitacionController::class, 'imagenPrincipal'])->name('tipo_habitacion_imagen_principal')->middleware(['auth', 'permission:tipo_habitacion_update']); //Para marcar la foto principal
});

//Habitaciones
Route::prefix("/habitacion")->group(function(){
    Route::get('/', [App\Http\Controllers\HabitacionesController::class, 'index'])->name('habitacion_index')->middleware(['auth', 'permission:habitacion_index']);                      //Para el index de habitaciones
    Route::get('/create', [App\Http\Controllers\HabitacionesController::class, 'create'])->name('habitacion_create')->middleware(['auth', 'permission:habitacion_create']);             //Para el create de habitaciones
    Route::post('/', [App\Http\Controllers\HabitacionesController::class, 'store'])->name('habitacion_store')->middleware(['auth', 'permission:habitacion_store']);                     //Para guardar la data del create
    Route::post('/estado/{id}', [App\Http\Controllers\HabitacionesController::class, 'estado'])->name('habitacion_estado')->middleware(['auth', 'permission:habitacion_estado']);       //Para cambiar el estado operativo (disponible, ocupada, mantenimiento)
    Route::get('/{id}', [App\Http\Controllers\HabitacionesController::class, 'show'])->name('habitacion_show')->middleware(['auth', 'permission:habitacion_show']);                     //Para el show de habitaciones
    Route::get('/{id}/edit', [App\Http\Controllers\HabitacionesController::class, 'edit'])->name('habitacion_edit')->middleware(['auth', 'permission:habitacion_edit']);                //Para el edit de habitaciones
    Route::put('/{id}', [App\Http\Controllers\HabitacionesController::class, 'update'])->name('habitacion_update')->middleware(['auth', 'permission:habitacion_update']);               //Para guardar la data del edit
    Route::post('/{id}', [App\Http\Controllers\HabitacionesController::class, 'destroy'])->name('habitacion_destroy')->middleware(['auth', 'permission:habitacion_destroy']);           //Para el eliminar (eliminado logico)
});

//Procesos en segundo plano
Route::prefix("/queue_control")->group(function(){
    Route::get('/', [App\Http\Controllers\QueueControlController::class, 'index'])->name('queue_control_index')->middleware(['auth', 'permission:queue_control_index']);
    Route::get('/updatePorcentaje', [App\Http\Controllers\QueueControlController::class, 'updatePorcentaje'])->name('queue_control_update_porcentaje')->middleware(['auth', 'permission:queue_control_update_porcentaje']);
});

// =============================================================================================================================================
// FIN RUTAS SEGUN PERMISOS



//=======================  EXAMPLE  =======================
//           ruta URL browser                              actionController        route para invocarla con {{ route('home') }}
//Route::get('/home', [\App\Http\Controllers\HomeController::class, 'home'])->name('home')->middleware('auth');
//=======================   END EXAMPLE  =======================
