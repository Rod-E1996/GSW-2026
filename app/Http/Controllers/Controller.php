<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function searchArrays($ArraySearch, $array){
    	foreach ($ArraySearch as $value) {
    		if( in_array($value, $array) )
    			return true;
    	}
    	return false;
    }

    public function auditar( $accion_id, $tabla_id, $despues = false, $antes = false,  $primariKey = false, $nombre_modificado = '', $nombre_user = '', $user_id = 0, $model_realizado_por = '\App\Models\User'){
        $auditar = new AuditarDetalle();
        if( $user_id != 0 ){
            $auditar->user_id = $user_id;
        }else{
            $auditar->user_id = auth()->user()->id ?? null;
        }
        if( $nombre_user == '' ){
            $auditar->name = auth()->user()->name ?? '';
            $auditar->email = auth()->user()->email ?? '';
        }else{
            $auditar->name = $nombre_user;
            $auditar->email =  '';
        }
        $auditar->auditar_accion_id = $accion_id;
        $auditar->auditar_tabla_id = $tabla_id;
        $auditar->nombre_modificado = $nombre_modificado;

        if( is_string($model_realizado_por) ){
            $auditar->model_realizado_por = $model_realizado_por;
        }else{
            $auditar->model_realizado_por = get_class($model_realizado_por);
        }

        if($despues)
            $auditar->despues = $despues;
        if($antes)
            $auditar->antes = $antes;
        if($primariKey)
            $auditar->primary_key = $primariKey;
        $auditar->save();
    }

    public function limpiarString ($string) {
        $string = str_replace(['á', 'Á'], 'A', $string);
        $string = str_replace(['é', 'É'], 'E', $string);
        $string = str_replace(['í', 'Í'], 'I', $string);
        $string = str_replace(['ó', 'Ó'], 'O', $string);
        $string = str_replace(['ú', 'Ú'], 'U', $string);
        $string = str_replace([' '], '', $string);
        return strtoupper($string);
    }
}
