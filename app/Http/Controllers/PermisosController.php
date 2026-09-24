<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\AuditarDetalle;
use Spatie\Permission\Models\Permission;

class PermisosController extends Controller
{
    //Funcion para mostrar el index de los permisos
    public function index(Request $request)
    {
        $columna1 = array();
        $columna2 = array();

        //Permisos actuales del sistema
        $permisos_actuales = Permission::pluck('name')->toArray();

        //Para obtener todas las rutas
        $routes = Route::getRoutes();

        //Set Columna 1
        foreach ($routes as $route) {
            if ($route->middleware()[0] === 'web' && $route->getName()) {
                if(!in_array($route->getName(), $permisos_actuales)){  //Para verificar si la ruta ya existe en la tabla
                    $columna1[] = array('value' => $route->getName(), 'text' => $route->getName());

                }
            }
        }

        //Set Columna 2
        foreach($permisos_actuales as $val){
            $columna2[] = array('value' => $val, 'text' => $val);
        }

        $data['columna1'] = $columna1;
        $data['columna2'] = $columna2;
        return view('admin.permisos.index' , $data);
    }


    public function move(Request $request)
    {
        //Variables
        $permisos_asignados_to_rol = array();
        $permisos_no_borrar = array();
        $borrados = 0;
        $agregados = 0;
        $mensaje = "";
        $type = true;

        $permisos_nuevos = $request['items'] ?? [];

        //Obtener los permisos actuales
        $permisos_actuales = Permission::pluck('name')->toArray();

        //Obtener el array de permisos que ya estan asignados a un rol (Estos no se podran borrar)
        foreach($permisos_actuales AS $val){
            $permission = Permission::findByName($val);

            if ($permission->hasPermissionTo($val)) {
                array_push($permisos_asignados_to_rol, $val);
            }
        }

        //Agregar los nuevos permisos si no existen
        foreach($permisos_nuevos AS $val){
            $existe_permiso = Permission::where('name', $val)->first();
            if(!$existe_permiso){
                Permission::create(['name' => $val]);
                $agregados++;
            }
        }

        //Eliminar los permisos que fueron removidos
        foreach($permisos_actuales AS $val1){
            if(!in_array($val1, $permisos_nuevos) && !in_array($val1, $permisos_asignados_to_rol)){  //Para verificar si es posible eliminar
                $permission = Permission::findByName($val1);
                $permission->delete();
                $borrados++;
            }else if(!in_array($val1, $permisos_nuevos)){
                array_push($permisos_no_borrar, $val1);
            }
        }

        //Construccion del mensaje
        if($agregados > 0){
            $mensaje .= $agregados. ($agregados > 1 ? " Permisos agregados." : " Permiso agregado.");
        }

        if($borrados > 0){
            $mensaje .= $borrados. ($borrados > 1 ? " Permisos eliminados.<br>" : " Permiso eliminado.<br>");
        }

        if($permisos_no_borrar){
            $type = false;
            $mensaje .= 'No puede eliminar estos permisos:<br>● ' . implode('<br>● ', $permisos_no_borrar);

        }else if($agregados == 0 && $borrados == 0){
            $mensaje .= "Ninguna accion realizada";
        }


        return response()->json(['success' => $type, 'text' => $mensaje]);
    }

}
