<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QueueControl;

class QueueControlController extends Controller
{
    //Funcion para mostrar el index
    public function index(Request $request)
    {
        $keyword = $request->get('search');

        $perPage = 10;

        $query = QueueControl::query();
        $query->where('estado', 1);

        if($keyword){
            $query->where('titulo', 'LIKE', "%$keyword%");
        }

        $data['queue_controls'] = $query->latest()->paginate($perPage);
        return view('admin.queue_control.index' , $data);
    }

    public function updatePorcentaje(Request $request)
    {
        $id = $request['id'];
        $queue_control = QueueControl::find($id);
        $porcentaje = ($queue_control->progreso / $queue_control->total_procesos ) * 100;

        return response()->json(['pendiente' => $queue_control->pendiente, 'progreso' => $queue_control->progreso, 'porcentaje' => number_format($porcentaje, 2, ".", "")]);
    }
}
