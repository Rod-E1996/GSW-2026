<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;

class TiposHabitacionController extends Controller
{
    //Reglas de validacion para las imagenes que se suben con el formulario
    private $reglasImagenes = [
        'imagenes' => ['nullable', 'array', 'max:10'],
        'imagenes.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
    ];

    //Funcion para mostrar el index
    public function index(Request $request)
    {
        $nombre = $request->get('nombre');
        $capacidad = $request->get('capacidad');

        $perPage = 10;

        $query = TipoHabitacion::query()->with('imagenPrincipal');
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
            TipoHabitacion::rules() + $this->reglasImagenes
        );

        $tipoHabitacion = new TipoHabitacion();
        $tipoHabitacion->nombre = $request->nombre;
        $tipoHabitacion->capacidad = $request->capacidad;
        $tipoHabitacion->precio_base = $request->precio_base;
        $tipoHabitacion->descripcion = $request->descripcion;

        if( $tipoHabitacion->save() ){
            $this->guardarImagenes($tipoHabitacion, $request->file('imagenes', []));
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
        $tipoHabitacion = TipoHabitacion::with('imagenes')->where('estado', 1)->find($id);
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
        $tipoHabitacion = TipoHabitacion::with('imagenes')->where('estado', 1)->find($id);
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
            TipoHabitacion::rules($id) + $this->reglasImagenes
        );

        $tipoHabitacion = TipoHabitacion::find($id);
        $tipoHabitacion->nombre = $request->nombre;
        $tipoHabitacion->capacidad = $request->capacidad;
        $tipoHabitacion->precio_base = $request->precio_base;
        $tipoHabitacion->descripcion = $request->descripcion;

        if($tipoHabitacion->save()){
            $this->guardarImagenes($tipoHabitacion, $request->file('imagenes', []));
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

        //No se puede eliminar un tipo que todavia tiene habitaciones activas
        $habitacionesActivas = $tipoHabitacion->habitaciones()->where('estado', 1)->count();
        if($habitacionesActivas > 0){
            return redirect('tipo_habitacion')->with([
                'alerta' => 'No se puede eliminar: hay ' . $habitacionesActivas . ' habitación(es) de este tipo. Reasígnelas o elimínelas primero.',
                'tipo' => 'error'
            ]);
        }

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

    //Funcion para eliminar una imagen del tipo de habitacion (archivo y registro)
    public function imagenDestroy($id, $imagen_id)
    {
        $imagen = TipoHabitacionImagen::where('tipo_habitacion_id', $id)->find($imagen_id);
        if(!$imagen){
            return back()->with([
                'alerta' => 'La imagen que esta buscando no existe.',
                'tipo' => 'error'
            ]);
        }

        $eraPrincipal = $imagen->principal;
        $imagen->eliminarArchivo();
        $imagen->delete();

        //Si se elimino la principal, la primera que quede pasa a ser principal
        if($eraPrincipal){
            $siguiente = TipoHabitacionImagen::where('tipo_habitacion_id', $id)->orderBy('orden')->orderBy('id')->first();
            if($siguiente){
                $siguiente->principal = true;
                $siguiente->save();
            }
        }

        return back()->with('alerta', 'Imagen eliminada con éxito.');
    }

    //Funcion para marcar una imagen como principal (la que se muestra en el sitio publico)
    public function imagenPrincipal($id, $imagen_id)
    {
        $imagen = TipoHabitacionImagen::where('tipo_habitacion_id', $id)->find($imagen_id);
        if(!$imagen){
            return back()->with([
                'alerta' => 'La imagen que esta buscando no existe.',
                'tipo' => 'error'
            ]);
        }

        TipoHabitacionImagen::where('tipo_habitacion_id', $id)->where('principal', true)->update(['principal' => false]);
        $imagen->principal = true;
        $imagen->save();

        return back()->with('alerta', 'Imagen principal actualizada.');
    }

    //Guarda en disco las imagenes subidas y crea sus registros.
    //La primera imagen del tipo queda como principal automaticamente.
    private function guardarImagenes(TipoHabitacion $tipoHabitacion, $archivos)
    {
        if(empty($archivos)){
            return;
        }

        $tienePrincipal = $tipoHabitacion->imagenes()->where('principal', true)->exists();
        $orden = (int) $tipoHabitacion->imagenes()->max('orden');

        foreach($archivos as $archivo){
            if(!$archivo || !$archivo->isValid()){
                continue;
            }

            $ruta = $archivo->store(TipoHabitacionImagen::CARPETA . '/' . $tipoHabitacion->id, TipoHabitacionImagen::DISCO);
            $orden++;

            TipoHabitacionImagen::create([
                'tipo_habitacion_id' => $tipoHabitacion->id,
                'ruta' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'orden' => $orden,
                'principal' => !$tienePrincipal,
            ]);

            $tienePrincipal = true;
        }
    }

}
