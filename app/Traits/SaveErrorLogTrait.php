<?php

namespace App\Traits;

use App\Models\ErrorLog;
use App\Models\User;
use App\Jobs\SendEmail;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

trait SaveErrorLogTrait {

    public static function saveErrorLog($routeData, $e){

        //para guardar el error en la DB
        $errorLog = new ErrorLog();
        $errorLog->controller = $routeData->action['controller'];
        $errorLog->mensaje = $e->getMessage();
        $errorLog->parametros = json_encode($routeData->parameters);
        $errorLog->user_id = auth()->user()->id ?? null;

        $errorLog->save();

        //Para guardar el error en los logs de laravel
        \Log::error($e);

        //Para obtener los correos de todos los usuarios con (ROL Administrador)
        $adminRol = Role::where('name', 'Administrador')->first();
        $correosAdmin = $adminRol->users->where('estado', 1)->pluck('email');

        //Envio del error por correo electronico a todos los administradores (Mediante  JOBS Y COLAS en segundo plano)
        SendEmail::dispatch($correosAdmin, $errorLog);

        if(env('WS_API_URL')){
            //Definicion del mensaje de whatsapp
            $usuario = $errorLog->user->name ?? '(Sin usuario)';
            $mensaje =  "⚠️ *Ha ocurrido un error* ⚠️".
                        "\n\n*Controller:* ".$errorLog->controller.
                        "\n\n*Mensaje:* ".$errorLog->mensaje.
                        "\n\n*Parametros:* ".$errorLog->parametros.
                        "\n\n*Usuario:* ".$usuario.
                        "\n*Fecha:* ".\Carbon\Carbon::parse($errorLog->created_at)->format('Y-m-d').
                        "\n*Hora:* ".\Carbon\Carbon::parse($errorLog->created_at)->format('h:i:s A');

            //Para obtener los telefonos de todos los usuarios con (ROL Administrador)
            $telefonosAdmin = $adminRol->users->where('estado', 1)->whereNotNull('phone')->pluck('phone');

            //Para realizar el envio del mensaje de whastapp al numero de cada administrador del sistema
            foreach($telefonosAdmin AS $val){
                Http::post(env('WS_API_URL'), ["message" => $mensaje, "phone" => $val]);
            }
        }

    }

}
