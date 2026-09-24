<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Session;
use App\Models\SessionLog;
// clases para cambiar las validaciones por defecto
use Illuminate\Validation\Rule;
use App\Rules\ContrasenaAnterior;
use Illuminate\Support\Facades\Session as LaravelSession;

class PerfilController extends Controller
{
    public function show(Request $request){

        $data['sessiones_usuario'] = Session::where('user_id', auth()->user()->id)->get();
        $data['session_actual'] = Session::find(LaravelSession::getId());

        $sessiones_log = SessionLog::where('user_id', auth()->user()->id)->latest('id')->get()->unique('device_model');

        if (!$sessiones_log->contains('session_id', $data['session_actual']->id)) { //Si se abre otra session en incognito
            $session_actual = SessionLog::where('user_id', auth()->user()->id)->where('session_id', $data['session_actual']->id)->first();
            $sessiones_log->push($session_actual);
        }

        $data['sessiones_log'] = $sessiones_log;
        $data['usuario'] = auth()->user();
        return view('admin.perfil.index', $data);
    }

    public function edit(Request $request){
        $user = auth()->user();
        $request->validate([
            'name' => [ 'required', 'string', 'max:255' ],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id) ],
        ]);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = preg_replace('/[^0-9]/', '', $request->phone); //Limpiar el string y dejar solo numeros
        $user->save();

        return back()->with('alerta', 'Perfil editado con éxito.');
    }

    public function editar_contrasena(Request $request){
        $user = auth()->user();
        $request->validate([
            'password' => ['required', 'string', 'confirmed'],
            'current_password' => new ContrasenaAnterior(),
        ]);
        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with([
            'alerta'=> 'Contraseña editada con éxito.',
            'tiempo'=> '2000'
        ]);
    }

    public function cerrarSession(Request $request)
    {
        $sessionId = $request->input('session_id');
        Session::where('id', $sessionId)->delete();

        return redirect()->back()->with('alerta', 'Eliminado con éxito.');
    }

}
