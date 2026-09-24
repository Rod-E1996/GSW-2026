<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\Session;
use App\Models\SessionLog;
use App\Services\SessionesService;

class UsuariosController extends Controller
{
    //Funcion para mostrar todos los usuarios
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 10;

        $query = User::query();
        if ( !empty( $keyword ) ) {
            $query->where('name', 'LIKE', "%$keyword%")
                   ->orWhere('email', 'LIKE', "%$keyword%");
        }

        $data['usuarios'] = $query->latest()->paginate($perPage);

        //Contador de sesiones abiertas por usuario (solo si se tiene permiso para verlas)
        $data['sesiones_por_usuario']   = [];
        $data['total_sesiones_activas'] = 0;
        if (auth()->user()->can('usuario_sessiones') || auth()->user()->can('session_index')) {
            $sessiones = app(SessionesService::class);
            $data['sesiones_por_usuario'] = $sessiones->activas()
                ->whereIn('user_id', $data['usuarios']->pluck('id'))
                ->selectRaw('user_id, COUNT(*) as total')
                ->groupBy('user_id')
                ->pluck('total', 'user_id')
                ->toArray();
            $data['total_sesiones_activas'] = $sessiones->activas()->count();
        }

        return view('admin.usuarios.index' , $data);
    }

    //Funcion para cargar el formulario de creacion
    public function create()
    {
        $data['roles'] = Role::get();
        return view('admin.usuarios.create', $data);
    }

    //Funcion para crear un nuevo usuario
    public function store(Request $request)
    {
        $request->validate(
            User::$rules
        );

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);

        if( $user->save() ){
            $user->syncRoles($request->id_rol); //Para asignar todos los roles seleccionados
            return back()->with('alerta', 'Agregado con éxito.');
        } else {
            return back()->with([
                'alerta' => 'Ocurrio un error al agregar.',
                'tipo' => 'error'
            ]);
        }
    }

    //Funcion para mostrar los datos de 1 usuario
    public function show($id)
    {
        $data['usuario'] = User::find($id);
        return view('admin.usuarios.show', $data);
    }

    //Funcion para cargar el formulario de actualizacion
    public function edit($id)
    {
        $data['usuario'] = User::find($id);
        $data['roles'] = Role::get();
        return view('admin.usuarios.update', $data);
    }

    //Funcion para actualizar los datos de un usuario
    public function update(Request $request, $id)
    {
        $request->validate(
            User::validateUpdate($id)
        );

        $user = User::find($id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->syncRoles($request->id_rol); //Para asignar todos los roles seleccionados

        // modificar la contraseña solo si se solicita
        if($request->password != '' && $request->password != null){
            $user->password = Hash::make($request->password);
        }

        if($user->save()){
            return redirect('usuario')->with('alerta', 'Modificado con éxito.');
        }else{
            return redirect('usuario')->with([
                'alerta' => 'Ocurrio un error al modificar.',
                'tipo' => 'error'
            ]);
        }
    }

    //Funcion para activar o desactivar un usuario
    public function estado( Request $request, $id )
    {
        $user = User::find($id);
        $user->estado = $request->estado!=1?0:1;
        $user->save();

        if( $user->estado == 1 ){
            return back()->with('alerta', 'Usuario activado con éxito.');
        }else{
            return back()->with('alerta', 'Usuario desactivado con éxito.');
        }
    }

    //Funcion para activar o desactivar las notificaciones de nuevos inicios de session
    public function estadoNotificacionLogin( Request $request, $id )
    {
        $user = User::find($id);
        $user->login_notificacion = $user->login_notificacion==1?0:1;
        $user->save();

        if( $user->login_notificacion == 1 ){
            return back()->with('alerta', 'Notificacion de nuevos inicios de sesión activadas con éxito.');
        }else{
            return back()->with('alerta', 'Notificacion de nuevos inicios de sesión desactivadas con éxito.');
        }
    }

    //Funcion para ver las sesiones abiertas de un usuario
    public function sessiones($id, SessionesService $sessiones)
    {
        $usuario = User::findOrFail($id);

        $activas = $sessiones->activasDeUsuario($usuario->id);

        $data['usuario']         = $usuario;
        $data['sessiones']       = $sessiones->detallar((clone $activas)->orderByDesc('last_activity')->get());
        $data['dispositivos']    = $sessiones->conteoPorDispositivo($activas);
        $data['lifetime']        = $sessiones->lifetime();
        $data['session_actual']  = $sessiones->sessionActualId();
        $data['ultima_conexion'] = SessionLog::where('user_id', $usuario->id)->latest('id')->first();
        $data['total_historico'] = SessionLog::where('user_id', $usuario->id)->count();

        //Historial completo de inicios de sesion (paginado con su propio parametro para no chocar con nada mas)
        $data['historial'] = SessionLog::where('user_id', $usuario->id)
            ->latest('id')
            ->paginate(15, ['*'], 'historial')
            ->withQueryString()
            ->fragment('historialSesiones');
        $data['historial_abierto'] = request()->has('historial');

        return view('admin.usuarios.sessiones', $data);
    }

    //Funcion para cerrar una sesion puntual de un usuario
    public function cerrarSession($id, $session_id, SessionesService $sessiones)
    {
        $usuario = User::findOrFail($id);
        $session = Session::where('user_id', $usuario->id)->find($session_id);

        if (!$session) {
            return back()->with([
                'alerta' => 'La sesión ya no existe o ya fue cerrada.',
                'tipo'   => 'warning',
            ]);
        }

        if ($session->id === $sessiones->sessionActualId()) {
            return back()->with([
                'alerta' => 'No puedes cerrar la sesión con la que estás navegando. Usa "Cerrar sesión" en tu perfil.',
                'tipo'   => 'warning',
            ]);
        }

        if ($sessiones->cerrar($session)) {
            return back()->with('alerta', 'Sesión cerrada con éxito.');
        }

        return back()->with([
            'alerta' => 'Ocurrió un error al cerrar la sesión.',
            'tipo'   => 'error',
        ]);
    }

    //Funcion para cerrar todas las sesiones de un usuario
    public function cerrarTodasSessiones($id, SessionesService $sessiones)
    {
        $usuario  = User::findOrFail($id);
        $cerradas = $sessiones->cerrarTodasDeUsuario($usuario->id);

        if ($cerradas === 0) {
            return back()->with([
                'alerta' => 'El usuario no tiene sesiones abiertas que se puedan cerrar.',
                'tipo'   => 'info',
            ]);
        }

        $texto = $cerradas === 1 ? 'Se cerró 1 sesión' : "Se cerraron $cerradas sesiones";
        return back()->with('alerta', "$texto de $usuario->name con éxito.");
    }

}
