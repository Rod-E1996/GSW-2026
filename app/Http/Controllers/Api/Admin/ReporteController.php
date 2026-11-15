<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoHabitacion;
use App\Services\ReporteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

//Reporte de ocupacion e ingresos para el panel y su exportacion a PDF
class ReporteController extends Controller
{
    public function __construct(private ReporteService $reportes)
    {
    }

    public function index(Request $request)
    {
        return response()->json([
            'data' => $this->reportes->generar(...$this->filtros($request)),
            'tipos' => TipoHabitacion::where('estado', 1)->orderBy('orden')->get(['id', 'nombre']),
        ]);
    }

    public function pdf(Request $request)
    {
        $reporte = $this->reportes->generar(...$this->filtros($request));

        $pdf = Pdf::loadView('reportes.ocupacion', ['reporte' => $reporte, 'generado' => now()])
            ->setPaper('letter')
            ->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte-' . $reporte['periodo']['desde'] . '-al-' . $reporte['periodo']['hasta'] . '.pdf"',
        ]);
    }

    private function filtros(Request $request): array
    {
        $datos = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'tipo_habitacion_id' => ['nullable', 'integer', 'exists:tipos_habitacion,id'],
        ]);

        if (Carbon::parse($datos['desde'])->diffInDays(Carbon::parse($datos['hasta'])) + 1 > ReporteService::MAX_DIAS) {
            throw ValidationException::withMessages(['hasta' => 'El periodo no puede superar un año.']);
        }

        return [$datos['desde'], $datos['hasta'], isset($datos['tipo_habitacion_id']) ? (int) $datos['tipo_habitacion_id'] : null];
    }
}
