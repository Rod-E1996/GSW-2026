<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * API de roles para el panel React (CRUD + asignacion de permisos).
 */
class RoleController extends Controller
{
    // Permisos que el rol Super Administrador no puede perder (mismo criterio que el Blade).
    private const PERMISOS_FIJOS_SUPER_ADMIN = [
        'permiso_index', 'permiso_move',
        'role_index', 'role_show', 'role_create', 'role_store', 'role_edit', 'role_update', 'role_destroy', 'role_move_permiso',
        'usuario_index', 'usuario_show', 'usuario_create', 'usuario_store', 'usuario_edit', 'usuario_update', 'usuario_estado',
        'dashboard',
    ];

    //Listado paginado con busqueda por nombre y conteo de permisos.
    public function index(Request $request)
    {
        $query = Role::query()->withCount('permissions');

        if ($keyword = $request->get('search')) {
            $query->where('name', 'LIKE', "%$keyword%");
        }

        return RoleResource::collection($query->latest()->paginate(10));
    }

    //Crea un rol (guard por defecto = web, igual que los existentes).
    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:roles,name'],
        ]);

        // guard_name explicito: bajo peticion Sanctum el guard por defecto seria
        // "sanctum", pero roles y permisos del sistema viven en el guard "web".
        $role = Role::create(['name' => $datos['name'], 'guard_name' => 'web']);

        return (new RoleResource($role))->response()->setStatusCode(201);
    }

    //Detalle del rol con permisos asignados y disponibles (para la pantalla de asignacion).
    public function show($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(['message' => 'El rol no existe.'], 404);
        }

        $asignados = $role->permissions()->orderBy('name')->pluck('name')->values();
        $todos = Permission::orderBy('name')->pluck('name');
        $disponibles = $todos->diff($asignados)->values();

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permisos_asignados' => $asignados,
                'permisos_disponibles' => $disponibles,
            ],
        ]);
    }

    //Actualiza el nombre del rol.
    public function update(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(['message' => 'El rol no existe.'], 404);
        }

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('roles', 'name')->ignore($role->id)],
        ]);

        $role->name = $datos['name'];
        $role->save();

        return new RoleResource($role);
    }

    //Elimina un rol solo si no tiene usuarios asignados.
    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(['message' => 'El rol no existe.'], 404);
        }

        $usuarios = User::whereHas('roles', fn ($q) => $q->where('name', $role->name))->count();

        if ($usuarios > 0) {
            return response()->json([
                'message' => 'No se puede eliminar este rol: hay usuarios asignados a él.',
            ], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Rol eliminado con éxito.']);
    }

    //Sincroniza los permisos del rol (equivale al movePermisos del Blade).
    public function permisos(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(['message' => 'El rol no existe.'], 404);
        }

        $datos = $request->validate([
            'permisos' => ['present', 'array'],
            'permisos.*' => ['string', 'exists:permissions,name'],
        ]);

        $nuevos = $datos['permisos'];
        $fijosReinsertados = [];

        // El Super Administrador no puede perder sus permisos clave.
        if ($role->name === 'Super Administrador') {
            $fijosReinsertados = array_values(array_diff(self::PERMISOS_FIJOS_SUPER_ADMIN, $nuevos));
            if (count($fijosReinsertados) > 0) {
                $nuevos = array_values(array_unique(array_merge($nuevos, $fijosReinsertados)));
            }
        }

        $role->syncPermissions($nuevos);

        $mensaje = count($fijosReinsertados) > 0
            ? 'Permisos actualizados. Algunos permisos del Super Administrador no se pueden quitar y se conservaron.'
            : 'Permisos actualizados con éxito.';

        return response()->json([
            'success' => count($fijosReinsertados) === 0,
            'message' => $mensaje,
            'permisos_fijos' => $fijosReinsertados,
            'permisos_asignados' => $role->permissions()->orderBy('name')->pluck('name')->values(),
        ]);
    }
}
