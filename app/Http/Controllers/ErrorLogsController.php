<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ErrorLog;
use App\Models\AuditarAccion;
use App\Models\AuditarTabla;
use Illuminate\Support\Facades\DB;

class ErrorLogsController extends Controller
{
    public function crearError($id)
    {
        try{

            DB::beginTransaction();

                $auditar = new AuditarTabla();
                $auditar->tabla = 'nueva tabla';
                $auditar->modelo = 'nueva tabla';
                $auditar->save();

                //Si es un error controlado aqui se agrega el mensaje de error que despues se mostrara en el catch
                //throw new \Exception($error_message = 'Mensaje de error controlado.');

                $auditarAccion = new AuditarAccion();
                //$auditarAccion->nombre = 'nueva accion';
                $auditarAccion->save();

            DB::commit();

        }catch(\Exception $e){
            DB::rollBack();

            if(empty($error_message)){
                \App\Traits\SaveErrorLogTrait::saveErrorLog(\Route::current(), $e);
            }

            return redirect('error_log')->with([
                'alerta' => $error_message ?? 'Ocurrio un error fatal.',
                'tipo' => 'warning'
            ]);
        }

        return redirect('error_log')->with('alerta', 'Error log generado con éxito.');

    }

    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 10;
        $query = ErrorLog::query();
        if ( !empty( $keyword ) ) {
            $query->where(function ($que) use ($keyword){
                $que->where('controller', 'LIKE', "%$keyword%");
                $que->orwhere('mensaje', 'LIKE', "%$keyword%");
                $que->orwhere('parametros', 'LIKE', "%$keyword%");
                $que->orwhereHas('user', function($q) use($keyword){
                    $q->where('name', 'LIKE', "%$keyword%");
                });
            });
        }
        $data['error_log'] = $query->orderBy('estado', 'asc')->orderBy('id', 'desc')->paginate($perPage);
        return view('admin.error_log.index' , $data);
    }

    public function show($id){
        $data['error_log'] = ErrorLog::find($id);
        return view('admin.error_log.show', $data);
    }

    public function estado($id){

        $errorLog = ErrorLog::find($id);

        $errorLog->estado = 1;
        $errorLog->save();

        if($errorLog->save()){
            return redirect('error_log')->with('alerta', 'Resuelto con éxito.');
        }else{
            return redirect('error_log')->with([
                'alerta' => 'Ocurrio un error al verificar.',
                'tipo' => 'error'
            ]);
        }
    }
}
