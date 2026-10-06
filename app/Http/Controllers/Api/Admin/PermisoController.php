<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermisoResource;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

/**
 * API del catalogo de permisos para el panel React (listar, crear, eliminar).
 * El Blade autodescubria permisos escaneando rutas web; en el SPA se gestiona
 * el catalogo de forma explicita.
 */
class PermisoController extends Controller
{
    //Listado paginado con busqueda por nombre y conteo de roles que lo usan.
    public function index(Request $request)
    {
        $query = Permission::query()->withCount('roles');

        if ($keyword = $request->get('search')) {
            $query->where('name', 'LIKE', "%$keyword%");
        }

        return PermisoResource::collection($query->orderBy('name')->paginate(15));
    }

    //Crea un permiso nuevo (guard web, igual que los existentes).
    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:permissions,name'],
        ]);

        $permiso = Permission::create(['name' => $datos['name'], 'guard_name' => 'web']);

        return (new PermisoResource($permiso))->response()->setStatusCode(201);
    }

    //Elimina un permiso solo si no esta asignado a ningun rol ni usuario.
    public function destroy($id)
    {
        $permiso = Permission::find($id);

        if (!$permiso) {
            return response()->json(['message' => 'El permiso no existe.'], 404);
        }

        if ($permiso->roles()->count() > 0 || $permiso->users()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar: el permiso está asignado a uno o más roles o usuarios.',
            ], 422);
        }

        $permiso->delete();

        return response()->json(['message' => 'Permiso eliminado con éxito.']);
    }
}
