<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTipoHabitacionRequest;
use App\Http\Requests\UpdateTipoHabitacionRequest;
use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;
use App\Services\TipoHabitacionImagenService;
use App\Traits\RespuestasCrud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TiposHabitacionController extends Controller
{
    use RespuestasCrud;

    public function __construct(private TipoHabitacionImagenService $imagenes)
    {
    }

    //Funcion para mostrar el index
    public function index(Request $request)
    {
        $perPage = 10;

        $query = TipoHabitacion::query()->with('imagenPrincipal')->where('estado', 1);

        if ($nombre = $request->get('nombre')) {
            $query->where('nombre', 'LIKE', "%$nombre%");
        }

        if ($capacidad = $request->get('capacidad')) {
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
    public function store(StoreTipoHabitacionRequest $request)
    {
        DB::transaction(function () use ($request) {
            $tipoHabitacion = TipoHabitacion::create(
                $request->safe()->only(['nombre', 'capacidad', 'precio_base', 'descripcion'])
            );
            $this->imagenes->guardar($tipoHabitacion, $request->file('imagenes', []));
        });

        return $this->exito('tipo_habitacion_index', 'Agregado con éxito.');
    }

    //Funcion para mostrar los datos de un registro
    public function show($id)
    {
        $tipoHabitacion = TipoHabitacion::with('imagenes')->where('estado', 1)->find($id);
        if (!$tipoHabitacion) {
            return $this->error('tipo_habitacion_index', 'El registro que esta buscando no existe.');
        }

        $data['tipo_habitacion'] = $tipoHabitacion;
        return view('admin.tipos_habitacion.show', $data);
    }

    //Funcion para cargar el formulario de actualizacion
    public function edit($id)
    {
        $tipoHabitacion = TipoHabitacion::with('imagenes')->where('estado', 1)->find($id);
        if (!$tipoHabitacion) {
            return $this->error('tipo_habitacion_index', 'El registro que esta buscando no existe.');
        }

        $data['tipo_habitacion'] = $tipoHabitacion;
        return view('admin.tipos_habitacion.update', $data);
    }

    //Funcion para actualizar los datos de un registro
    public function update(UpdateTipoHabitacionRequest $request, $id)
    {
        $tipoHabitacion = TipoHabitacion::where('estado', 1)->find($id);
        if (!$tipoHabitacion) {
            return $this->error('tipo_habitacion_index', 'El registro que esta buscando no existe.');
        }

        DB::transaction(function () use ($request, $tipoHabitacion) {
            $tipoHabitacion->update(
                $request->safe()->only(['nombre', 'capacidad', 'precio_base', 'descripcion'])
            );
            $this->imagenes->guardar($tipoHabitacion, $request->file('imagenes', []));
        });

        return $this->exito('tipo_habitacion_index', 'Modificado con éxito.');
    }

    //Funcion para eliminar (Solo cambio de estado)
    public function destroy($id)
    {
        $tipoHabitacion = TipoHabitacion::where('estado', 1)->find($id);
        if (!$tipoHabitacion) {
            return $this->error('tipo_habitacion_index', 'El registro que esta buscando no existe.');
        }

        //No se puede eliminar un tipo que todavia tiene habitaciones activas
        $habitacionesActivas = $tipoHabitacion->habitaciones()->where('estado', 1)->count();
        if ($habitacionesActivas > 0) {
            return $this->error(
                'tipo_habitacion_index',
                'No se puede eliminar: hay ' . $habitacionesActivas . ' habitación(es) de este tipo. Reasígnelas o elimínelas primero.'
            );
        }

        $tipoHabitacion->estado = 0;
        $tipoHabitacion->save();

        return $this->exito('tipo_habitacion_index', 'Eliminado con éxito.');
    }

    //Funcion para eliminar una imagen del tipo de habitacion (archivo y registro)
    public function imagenDestroy($id, $imagen_id)
    {
        $imagen = TipoHabitacionImagen::where('tipo_habitacion_id', $id)->find($imagen_id);
        if (!$imagen) {
            return $this->errorAtras('La imagen que esta buscando no existe.');
        }

        $this->imagenes->eliminar($imagen);

        return back()->with('alerta', 'Imagen eliminada con éxito.');
    }

    //Funcion para marcar una imagen como principal (la que se muestra en el sitio publico)
    public function imagenPrincipal($id, $imagen_id)
    {
        $imagen = TipoHabitacionImagen::where('tipo_habitacion_id', $id)->find($imagen_id);
        if (!$imagen) {
            return $this->errorAtras('La imagen que esta buscando no existe.');
        }

        $this->imagenes->marcarPrincipal($imagen);

        return back()->with('alerta', 'Imagen principal actualizada.');
    }
}
