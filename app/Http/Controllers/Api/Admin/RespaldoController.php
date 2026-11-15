<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Respaldo;
use App\Services\RespaldoService;
use Illuminate\Http\Request;

//Respaldos de la base desde el panel: listado, generacion manual y descarga
class RespaldoController extends Controller
{
    public function __construct(private RespaldoService $respaldos)
    {
    }

    public function index()
    {
        return response()->json([
            'data' => Respaldo::with('user')->latest('id')->limit(50)->get()->map(fn (Respaldo $r) => $this->fila($r)),
        ]);
    }

    public function store(Request $request)
    {
        $respaldo = $this->respaldos->generar($request->user());

        return response()->json(['message' => 'Respaldo generado.', 'data' => $this->fila($respaldo->load('user'))], 201);
    }

    public function descargar(int $id)
    {
        $respaldo = Respaldo::findOrFail($id);
        $ruta = $this->respaldos->ruta($respaldo);

        abort_unless(is_file($ruta), 404, 'El archivo del respaldo ya no existe.');

        return response()->download($ruta, $respaldo->archivo, ['Content-Type' => 'application/sql']);
    }

    private function fila(Respaldo $r): array
    {
        return [
            'id' => $r->id,
            'archivo' => $r->archivo,
            'tamano' => (int) $r->tamano,
            'origen' => $r->origen,
            'usuario' => $r->user?->name,
            'fecha' => $r->created_at?->toIso8601String(),
        ];
    }
}
