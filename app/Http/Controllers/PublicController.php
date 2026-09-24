<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home()
    {
        return view('public.home');
        //return redirect('login'); //Para redirigir al login en vez de la pagina publica
    }

}
