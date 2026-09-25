<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TipoHabitacion;

class PublicController extends Controller
{
    //Pagina principal del sitio publico: presentacion del hotel, tipos de habitacion,
    //servicios, galeria y contacto
    public function home()
    {
        $data['hotel'] = config('hotel');

        //Tipos de habitacion activos con sus fotos, ordenados del mas economico al mas caro
        $data['tipos_habitacion'] = TipoHabitacion::with(['imagenes', 'imagenPrincipal'])
            ->where('estado', 1)
            ->orderBy('precio_base')
            ->get();

        return view('public.home', $data);
        //return redirect('login'); //Para redirigir al login en vez de la pagina publica
    }

}
