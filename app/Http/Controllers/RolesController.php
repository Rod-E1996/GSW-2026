<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class RolesController extends Controller
{
    //Funcion para mostrar el index
    public function index(Request $request)
    {
        $search = $request->get('search');
        $perPage = 10;

        $query = Role::query();

        if($search){
            $query->where('name', 'LIKE', "%$search%");
        }

        $data['roles'] = $query->latest()->withCount('permissions')->paginate($perPage);
        return view('admin.roles.index' , $data);
    }

    //Funcion para cargar el formulario de creacion
    public function create()
    {
        return view('admin.roles.create');
    }

    //Funcion para crear un nuevo registro
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:191'],
        ]);

        $role = new Role();
        $role->name = $request->name;

        if( $role->save() ){
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
        $role = Role::find($id);

        if(!$role){
            return back()->with([
                'alerta' => 'El registro que esta buscando no existe.',
                'tipo' => 'error'
            ]);
        }

        $columna1 = array();
        $columna2 = array();

        $rol = Role::findById($id);
        $permisos_asignados = $rol->permissions()->pluck('name')->toArray();

        $permisos_actuales = Permission::pluck('name')->toArray();
        $permisos_no_asignados = array_diff($permisos_actuales, $permisos_asignados);

        foreach($permisos_no_asignados AS $val){
            $columna1[] = array('value' => $val, 'text' => $val);
        }

        foreach($permisos_asignados AS $val){
            $columna2[] = array('value' => $val, 'text' => $val);
        }

        $data['role'] = $role;
        $data['columna1'] = $columna1;
        $data['columna2'] = $columna2;
        return view('admin.roles.show', $data);
    }

    //Funcion para cargar el formulario de actualizacion
    public function edit($id)
    {
        $columna1 = array();
        $columna2 = array();

        $rol = Role::findById($id);
        $permisos_asignados = $rol->permissions()->pluck('name')->toArray();

        $permisos_actuales = Permission::pluck('name')->toArray();
        $permisos_no_asignados = array_diff($permisos_actuales, $permisos_asignados);

        foreach($permisos_no_asignados AS $val){
            $columna1[] = array('value' => $val, 'text' => $val);
        }

        foreach($permisos_asignados AS $val){
            $columna2[] = array('value' => $val, 'text' => $val);
        }

        $data['columna1'] = $columna1;
        $data['columna2'] = $columna2;
        $data['role'] = Role::find($id);
        return view('admin.roles.update', $data);
    }

    //Funcion para actualizar los datos de un usuario
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:191'],
        ]);

        $role = Role::find($id);
        $role->name = $request->name;

        if($role->save()){
            return redirect('role')->with('alerta', 'Modificado con éxito.');
        }else{
            return redirect('role')->with([
                'alerta' => 'Ocurrio un error al modificar.',
                'tipo' => 'error'
            ]);
        }
    }

    //Funcion para eliminar (Solo cambio de estado)
    public function destroy($id)
    {
        $role = Role::find($id);

        $users = User::whereHas('roles', function ($query) use ($role){
            $query->where('name', $role->name);
        })->get();

        if($users->count() > 0){
            return redirect('role')->with([
                'alerta' => 'No se puede eliminar este rol. Hay usuarios asignados a él.',
                'tipo' => 'error'
            ]);
        }

        if($role->delete()){
            return redirect('role')->with('alerta', 'Eliminado con éxito.');
        }else{
            return redirect('role')->with([
                'alerta' => 'Ocurrio un error al eliminar.',
                'tipo' => 'error'
            ]);
        }
    }

    public function movePermisos(Request $request)
    {
        $permisos_nuevos = $request['items'] ?? [];
        $id_rol = $request['id_rol'];
        $mensaje = '';
        $type = true;

        $rol = Role::findById($id_rol);
        $permisos_rol = $rol->getPermissionNames();

        //Validacion permisos de administrador irrevocables
        if($rol->name == 'Administrador'){
            $permisos_fijos = ['permiso_index','permiso_move','role_index','role_show','role_create','role_store','role_edit','role_update','role_destroy','role_move_permiso','usuario_index','usuario_show','usuario_create','usuario_store','usuario_edit','usuario_update','usuario_estado','dashboard'];
            $permisos_irrevocables = array_diff($permisos_fijos, $permisos_nuevos);

            if(count($permisos_irrevocables) > 0){
                $permisos_nuevos = array_merge($permisos_nuevos, $permisos_irrevocables);
                $mensaje_permisos_irrevocables = 'No puede eliminar estos permisos:<br>● ' . implode('<br>● ', $permisos_irrevocables);
                $type = false;
            }
        }

        $permisos_migrados = count($permisos_rol) - count($permisos_nuevos);

        if($permisos_migrados > 0){
            $mensaje .=  $permisos_migrados. ($permisos_migrados > 1 ? " Permisos eliminados.<br>" : " Permiso eliminado.<br>");
        }

        if($permisos_migrados < 0){
            $permisos_migrados = ($permisos_migrados * -1);
            $mensaje .=  $permisos_migrados. ($permisos_migrados > 1 ? " Permisos agregados.<br>" : " Permiso agregado.<br>");
        }

        if(!$type){
            $mensaje .= ($permisos_migrados != 0 ? '<br>' : ''). $mensaje_permisos_irrevocables;
        }

        if($permisos_migrados == 0 && $type == true){
            $mensaje .= "Ninguna accion realizada";
        }

        $rol->syncPermissions($permisos_nuevos);

        return response()->json(['success' => $type, 'text' => $mensaje]);
    }

}
