<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditarDetalleResource;
use App\Models\AuditarDetalle;
use Illuminate\Http\Request;

/**
 * API de auditoria (solo lectura) para el panel React.
 */
class AuditarController extends Controller
{
    //Listado paginado con busqueda por usuario, entidad o fecha.
    public function index(Request $request)
    {
        $query = AuditarDetalle::query()->with(['user', 'auditarAccion', 'auditarTabla']);

        if ($keyword = $request->get('search')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('created_at', 'LIKE', "%$keyword%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'LIKE', "%$keyword%"))
                    ->orWhereHas('auditarTabla', fn ($t) => $t->where('tabla', 'LIKE', "%$keyword%"));
            });
        }

        return AuditarDetalleResource::collection($query->latest()->paginate(10));
    }

    //Detalle de un registro (incluye antes/despues).
    public function show($id)
    {
        $registro = AuditarDetalle::with(['user', 'auditarAccion', 'auditarTabla'])->find($id);
        if (!$registro) {
            return response()->json(['message' => 'El registro no existe.'], 404);
        }

        return new AuditarDetalleResource($registro);
    }
}
