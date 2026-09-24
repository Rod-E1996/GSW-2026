<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditarDetalle;

class HomeController extends Controller
{
     /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function afterlogin()
    {
        // Aqui se redireccionara a determinada vista dependiendo del rol del usuario logueado
        if(auth()->user()->hasAnyRole(['Super Administrador', 'Administrador'])){
            return redirect('/dashboard');
        }else if(auth()->user()->hasRole('Invitado')){
            return redirect('/');
        }else{
            return redirect('/');
        }
    }

    public function dashboard()
    {
        return view('admin.dashboard.index');
    }

}
