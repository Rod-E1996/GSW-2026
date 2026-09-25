<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TipoHabitacion;

class TiposHabitacionController extends Controller
{
    //Funcion para mostrar el index
    public function index(Request $request)
    {
        $nombre = $request->get('nombre');
        $capacidad = $request->get('capacidad');

        $perPage = 10;

        $query = TipoHabitacion::query();
        $query->where('estado', 1);

        if($nombre){
            $query->where('nombre', 'LIKE', "%$nombre%");
        }

        if($capacidad){
            $query->where('capacidad', '>=', $capacidad);
        }

        $data['tipos_habitacion'] = $query->orderBy('nombre')->paginate($perPage);
        return view('admin.tipos_habitacion.index', $data);
    }

    //Funcion para cargar el formulario de creacion
    public function create()
    {
        return view('admin.tipos_habitacion.create');
    }

    //Funcion para crear un nuevo registro
    public function store(Request $request)
    {
        $request->validate(
            TipoHabitacion::rules()
        );

        $tipoHabitacion = new TipoHabitacion();
        $tipoHabitacion->nombre = $request->nombre;
        $tipoHabitacion->capacidad = $request->capacidad;
        $tipoHabitacion->precio_base = $request->precio_base;
        $tipoHabitacion->descripcion = $request->descripcion;

        if( $tipoHabitacion->save() ){
            return redirect('tipo_habitacion')->with('alerta', 'Agregado con éxito.');
        } else {
            return back()->with([
                'alerta' => 'Ocurrio un error al agregar.',
                'tipo' => 'error'
            ]);
        }
    }

    //Funcion para mostrar los datos de un registro
    public function show($id)
    {
        $tipoHabitacion = TipoHabitacion::where('estado', 1)->find($id);
        if(!$tipoHabitacion){
            return redirect('tipo_habitacion')->with([
                'alerta' => 'El registro que esta buscando no existe.',
                'tipo' => 'error'
            ]);
        }

        $data['tipo_habitacion'] = $tipoHabitacion;
        return view('admin.tipos_habitacion.show', $data);
    }

    //Funcion para cargar el formulario de actualizacion
    public function edit($id)
    {
        $tipoHabitacion = TipoHabitacion::where('estado', 1)->find($id);
        if(!$tipoHabitacion){
            return redirect('tipo_habitacion')->with([
                'alerta' => 'El registro que esta buscando no existe.',
                'tipo' => 'error'
            ]);
        }

        $data['tipo_habitacion'] = $tipoHabitacion;
        return view('admin.tipos_habitacion.update', $data);
    }

    //Funcion para actualizar los datos de un registro
    public function update(Request $request, $id)
    {
        $request->validate(
            TipoHabitacion::rules($id)
        );

        $tipoHabitacion = TipoHabitacion::find($id);
        $tipoHabitacion->nombre = $request->nombre;
        $tipoHabitacion->capacidad = $request->capacidad;
        $tipoHabitacion->precio_base = $request->precio_base;
        $tipoHabitacion->descripcion = $request->descripcion;

        if($tipoHabitacion->save()){
            return redirect('tipo_habitacion')->with('alerta', 'Modificado con éxito.');
        }else{
            return redirect('tipo_habitacion')->with([
                'alerta' => 'Ocurrio un error al modificar.',
                'tipo' => 'error'
            ]);
        }
    }

    //Funcion para eliminar (Solo cambio de estado)
    public function destroy($id)
    {
        $tipoHabitacion = TipoHabitacion::find($id);
        $tipoHabitacion->estado = 0;

        if($tipoHabitacion->save()){
            return redirect('tipo_habitacion')->with('alerta', 'Eliminado con éxito.');
        }else{
            return redirect('tipo_habitacion')->with([
                'alerta' => 'Ocurrio un error al eliminar.',
                'tipo' => 'error'
            ]);
        }
    }

}
