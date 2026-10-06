<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsuarioResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * API de usuarios para el panel React (CRUD + estado + aviso de login).
 * La gestion de sesiones por usuario vive en el modulo de Sesiones (tokens).
 */
class UserController extends Controller
{
    //Listado paginado con busqueda por nombre o email.
    public function index(Request $request)
    {
        $query = User::query()->with('roles');

        if ($keyword = $request->get('search')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%$keyword%")
                    ->orWhere('email', 'LIKE', "%$keyword%");
            });
        }

        return UsuarioResource::collection($query->latest()->paginate(10));
    }

    //Catalogo de roles para el selector del formulario.
    public function rolesDisponibles()
    {
        return response()->json([
            'data' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    //Crea un usuario y le asigna los roles seleccionados.
    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => Hash::make($datos['password']),
            'estado' => 1,
        ]);

        $user->syncRoles(Role::whereIn('id', $datos['roles'])->get());

        return (new UsuarioResource($user->load('roles')))->response()->setStatusCode(201);
    }

    //Detalle de un usuario (para la pantalla de ver/editar).
    public function show($id)
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return response()->json(['message' => 'El usuario no existe.'], 404);
        }

        return new UsuarioResource($user);
    }

    //Actualiza datos, roles y (opcionalmente) la contrasena.
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'El usuario no existe.'], 404);
        }

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->name = $datos['name'];
        $user->email = $datos['email'];

        if (!empty($datos['password'])) {
            $user->password = Hash::make($datos['password']);
        }

        $user->save();
        $user->syncRoles(Role::whereIn('id', $datos['roles'])->get());

        return new UsuarioResource($user->load('roles'));
    }

    //Activa o desactiva un usuario (no permite desactivar la propia cuenta).
    public function estado(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'El usuario no existe.'], 404);
        }

        if ((int) $user->id === (int) $request->user()->id) {
            return response()->json(['message' => 'No puedes cambiar el estado de tu propia cuenta.'], 422);
        }

        $user->estado = $user->estado == 1 ? 0 : 1;
        $user->save();

        return new UsuarioResource($user->load('roles'));
    }

    //Activa o desactiva el aviso de nuevos inicios de sesion del usuario.
    public function loginNotificacion($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'El usuario no existe.'], 404);
        }

        $user->login_notificacion = $user->login_notificacion == 1 ? 0 : 1;
        $user->save();

        return new UsuarioResource($user->load('roles'));
    }
}
