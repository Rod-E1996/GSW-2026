<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEstadoHabitacionRequest;
use App\Http\Requests\StoreHabitacionRequest;
use App\Http\Requests\UpdateHabitacionRequest;
use App\Models\Habitacion;
use App\Models\TipoHabitacion;
use App\Traits\RespuestasCrud;
use Illuminate\Http\Request;

class HabitacionesController extends Controller
{
    use RespuestasCrud;

    //Funcion para mostrar el index
    public function index(Request $request)
    {
        $perPage = 10;

        $query = Habitacion::query()->with('tipoHabitacion')->where('estado', 1);

        if ($numero = $request->get('numero')) {
            $query->where('numero', 'LIKE', "%$numero%");
        }

        if ($tipo_habitacion_id = $request->get('tipo_habitacion_id')) {
            $query->where('tipo_habitacion_id', $tipo_habitacion_id);
        }

        if ($estado_habitacion = $request->get('estado_habitacion')) {
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
    public function store(StoreHabitacionRequest $request)
    {
        Habitacion::create($request->validated());

        return $this->exito('habitacion_index', 'Agregado con éxito.');
    }

    //Funcion para mostrar los datos de un registro
    public function show($id)
    {
        $habitacion = Habitacion::with('tipoHabitacion')->where('estado', 1)->find($id);
        if (!$habitacion) {
            return $this->error('habitacion_index', 'El registro que esta buscando no existe.');
        }

        $data['habitacion'] = $habitacion;
        $data['estados'] = Habitacion::ESTADOS;
        return view('admin.habitaciones.show', $data);
    }

    //Funcion para cargar el formulario de actualizacion
    public function edit($id)
    {
        $habitacion = Habitacion::where('estado', 1)->find($id);
        if (!$habitacion) {
            return $this->error('habitacion_index', 'El registro que esta buscando no existe.');
        }

        $data['habitacion'] = $habitacion;
        $data['tipos_habitacion'] = TipoHabitacion::where('estado', 1)->orderBy('nombre')->get();
        $data['estados'] = Habitacion::ESTADOS;
        return view('admin.habitaciones.update', $data);
    }

    //Funcion para actualizar los datos de un registro
    public function update(UpdateHabitacionRequest $request, $id)
    {
        $habitacion = Habitacion::where('estado', 1)->find($id);
        if (!$habitacion) {
            return $this->error('habitacion_index', 'El registro que esta buscando no existe.');
        }

        $habitacion->update($request->validated());

        return $this->exito('habitacion_index', 'Modificado con éxito.');
    }

    //Funcion para cambiar solo el estado operativo (disponible, ocupada, mantenimiento)
    //Pensada para recepcion, que no edita el resto de los datos
    public function estado(CambiarEstadoHabitacionRequest $request, $id)
    {
        $habitacion = Habitacion::where('estado', 1)->find($id);
        if (!$habitacion) {
            return $this->error('habitacion_index', 'El registro que esta buscando no existe.');
        }

        $habitacion->estado_habitacion = $request->estado_habitacion;
        $habitacion->save();

        return back()->with('alerta', 'Habitación ' . $habitacion->numero . ' marcada como ' . strtolower($habitacion->estado_nombre) . '.');
    }

    //Funcion para eliminar (Solo cambio de estado)
    public function destroy($id)
    {
        $habitacion = Habitacion::where('estado', 1)->find($id);
        if (!$habitacion) {
            return $this->error('habitacion_index', 'El registro que esta buscando no existe.');
        }

        $habitacion->estado = 0;
        $habitacion->save();

        return $this->exito('habitacion_index', 'Eliminado con éxito.');
    }
}
