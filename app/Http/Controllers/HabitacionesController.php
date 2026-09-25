<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Habitacion;
use App\Models\TipoHabitacion;

class HabitacionesController extends Controller
{
    //Funcion para mostrar el index
    public function index(Request $request)
    {
        $numero = $request->get('numero');
        $tipo_habitacion_id = $request->get('tipo_habitacion_id');
        $estado_habitacion = $request->get('estado_habitacion');

        $perPage = 10;

        $query = Habitacion::query()->with('tipoHabitacion');
        $query->where('estado', 1);

        if($numero){
            $query->where('numero', 'LIKE', "%$numero%");
        }

        if($tipo_habitacion_id){
            $query->where('tipo_habitacion_id', $tipo_habitacion_id);
        }

        if($estado_habitacion){
            $query->where('estado_habitacion', $estado_habitacion);
        }

        $data['tipos_habitacion'] = TipoHabitacion::where('estado', 1)->orderBy('nombre')->get();
        $data['estados'] = Habitacion::ESTADOS;
        $data['habitaciones'] = $query->orderBy('piso')->orderBy('numero')->paginate($perPage);
        return view('admin.habitaciones.index', $data);
    }

    //Funcion para cargar el formulario de creacion
    public function create()
    {
        $data['tipos_habitacion'] = TipoHabitacion::where('estado', 1)->orderBy('nombre')->get();
        $data['estados'] = Habitacion::ESTADOS;
        return view('admin.habitaciones.create', $data);
    }

    //Funcion para crear un nuevo registro
    public function store(Request $request)
    {
        $request->validate(
            Habitacion::rules()
        );

        $habitacion = new Habitacion();
        $habitacion->numero = $request->numero;
        $habitacion->piso = $request->piso;
        $habitacion->tipo_habitacion_id = $request->tipo_habitacion_id;
        $habitacion->estado_habitacion = $request->estado_habitacion;
        $habitacion->descripcion = $request->descripcion;

        if( $habitacion->save() ){
            return redirect('habitacion')->with('alerta', 'Agregado con éxito.');
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
        $habitacion = Habitacion::with('tipoHabitacion')->where('estado', 1)->find($id);
        if(!$habitacion){
            return redirect('habitacion')->with([
                'alerta' => 'El registro que esta buscando no existe.',
                'tipo' => 'error'
            ]);
        }

        $data['habitacion'] = $habitacion;
        $data['estados'] = Habitacion::ESTADOS;
        return view('admin.habitaciones.show', $data);
    }

    //Funcion para cargar el formulario de actualizacion
    public function edit($id)
    {
        $habitacion = Habitacion::where('estado', 1)->find($id);
        if(!$habitacion){
            return redirect('habitacion')->with([
                'alerta' => 'El registro que esta buscando no existe.',
                'tipo' => 'error'
            ]);
        }

        $data['habitacion'] = $habitacion;
        $data['tipos_habitacion'] = TipoHabitacion::where('estado', 1)->orderBy('nombre')->get();
        $data['estados'] = Habitacion::ESTADOS;
        return view('admin.habitaciones.update', $data);
    }

    //Funcion para actualizar los datos de un registro
    public function update(Request $request, $id)
    {
        $request->validate(
            Habitacion::rules($id)
        );

        $habitacion = Habitacion::find($id);
        $habitacion->numero = $request->numero;
        $habitacion->piso = $request->piso;
        $habitacion->tipo_habitacion_id = $request->tipo_habitacion_id;
        $habitacion->estado_habitacion = $request->estado_habitacion;
        $habitacion->descripcion = $request->descripcion;

        if($habitacion->save()){
            return redirect('habitacion')->with('alerta', 'Modificado con éxito.');
        }else{
            return redirect('habitacion')->with([
                'alerta' => 'Ocurrio un error al modificar.',
                'tipo' => 'error'
            ]);
        }
    }

    //Funcion para cambiar solo el estado operativo (disponible, ocupada, mantenimiento)
    //Pensada para recepcion, que no edita el resto de los datos
    public function estado(Request $request, $id)
    {
        $request->validate([
            'estado_habitacion' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $habitacion = Habitacion::where('estado', 1)->find($id);
        if(!$habitacion){
            return redirect('habitacion')->with([
                'alerta' => 'El registro que esta buscando no existe.',
                'tipo' => 'error'
            ]);
        }

        $habitacion->estado_habitacion = $request->estado_habitacion;

        if($habitacion->save()){
            return back()->with('alerta', 'Habitación ' . $habitacion->numero . ' marcada como ' . strtolower($habitacion->estado_nombre) . '.');
        }else{
            return back()->with([
                'alerta' => 'Ocurrio un error al cambiar el estado.',
                'tipo' => 'error'
            ]);
        }
    }

    //Funcion para eliminar (Solo cambio de estado)
    public function destroy($id)
    {
        $habitacion = Habitacion::find($id);
        $habitacion->estado = 0;

        if($habitacion->save()){
            return redirect('habitacion')->with('alerta', 'Eliminado con éxito.');
        }else{
            return redirect('habitacion')->with([
                'alerta' => 'Ocurrio un error al eliminar.',
                'tipo' => 'error'
            ]);
        }
    }

}
