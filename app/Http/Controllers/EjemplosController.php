<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ejemplo;
use App\Models\AuditarAccion;

class EjemplosController extends Controller
{
    //Funcion para mostrar el index
    public function index(Request $request)
    {
        $nombre = $request->get('nombre');
        $fecha = $request->get('fecha');
        $select_quemado = $request->get('select_quemado');
        $auditar_accion_id = $request->get('auditar_accion_id');

        $perPage = 10;

        $query = Ejemplo::query();
        $query->where('estado', 1);

        if($nombre){
            $query->where('nombre', 'LIKE', "%$nombre%");
        }

        if($fecha){
            $query->where('fecha',  $fecha);
        }

        if($select_quemado){
            $query->where('select_quemado',  $select_quemado);
        }

        if($auditar_accion_id){
            $query->where('auditar_accion_id',  $auditar_accion_id);
        }


        $data['auditar_acciones'] =  AuditarAccion::get();
        $data['ejemplos'] = $query->latest()->paginate($perPage);
        return view('admin.ejemplos.index' , $data);
    }

    //Funcion para cargar el formulario de creacion
    public function create()
    {
        $data['auditar_acciones'] = AuditarAccion::get();
        return view('admin.ejemplos.create', $data);
    }

    //Funcion para crear un nuevo registro
    public function store(Request $request)
    {
        $request->validate(
            Ejemplo::$rules
        );

        $ejemplo = new Ejemplo();
        $ejemplo->nombre = $request->nombre;
        $ejemplo->telefono = $request->telefono;
        $ejemplo->fecha = $request->fecha;
        $ejemplo->cantidad = $request->cantidad;
        $ejemplo->porcentaje = $request->porcentaje;
        $ejemplo->select_quemado = $request->select_quemado;
        $ejemplo->auditar_accion_id = $request->auditar_accion_id;
        $ejemplo->descripcion = $request->descripcion;

        if( $ejemplo->save() ){
            return back()->with('alerta', 'Agregado con éxito.');
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
        $ejemplo = Ejemplo::where('estado', 1)->find($id);
        if(!$ejemplo){
            return back()->with([
                'alerta' => 'El registro que esta buscando no existe.',
                'tipo' => 'error'
            ]);
        }

        $data['ejemplo'] = $ejemplo;
        return view('admin.ejemplos.show', $data);
    }

    //Funcion para cargar el formulario de actualizacion
    public function edit($id)
    {
        $data['ejemplo'] = Ejemplo::find($id);
        $data['auditar_acciones'] = AuditarAccion::get();
        return view('admin.ejemplos.update', $data);
    }

    //Funcion para actualizar los datos de un usuario
    public function update(Request $request, $id)
    {
        $request->validate(
            Ejemplo::$rules
        );

        $ejemplo = Ejemplo::find($id);
        $ejemplo->nombre = $request->nombre;
        $ejemplo->telefono = $request->telefono;
        $ejemplo->fecha = $request->fecha;
        $ejemplo->cantidad = $request->cantidad;
        $ejemplo->porcentaje = $request->porcentaje;
        $ejemplo->select_quemado = $request->select_quemado;
        $ejemplo->auditar_accion_id = $request->auditar_accion_id;
        $ejemplo->descripcion = $request->descripcion;

        if($ejemplo->save()){
            return redirect('ejemplo')->with('alerta', 'Modificado con éxito.');
        }else{
            return redirect('ejemplo')->with([
                'alerta' => 'Ocurrio un error al modificar.',
                'tipo' => 'error'
            ]);
        }
    }

    //Funcion para eliminar (Solo cambio de estado)
    public function destroy($id)
    {
        $ejemplo = Ejemplo::find($id);
        $ejemplo->estado = 0;

        if($ejemplo->save()){
            return redirect('ejemplo')->with('alerta', 'Eliminado con éxito.');
        }else{
            return redirect('ejemplo')->with([
                'alerta' => 'Ocurrio un error al eliminar.',
                'tipo' => 'error'
            ]);
        }
    }

}
