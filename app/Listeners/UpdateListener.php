<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\UpdateEvent;
use App\Models\AuditarDetalle;
use App\Models\AuditarTabla;

class UpdateListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(UpdateEvent $event)
    {
        // dd( $event->model->getTable() );
        // dd( get_class($event->model) );
        $tabla = AuditarTabla::query();// obtener la tabla que se esta editando
        $tabla->where('modelo', get_class($event->model) );
        // cuando se esta editando el perfil
        if( request()->is(['perfil', 'perfil/*']) )
            $tabla->where('tabla', 'perfil');
        $tabla = $tabla->first();

        if( !$tabla ){
            $tabla = AuditarTabla::create([
                'tabla' =>  $event->model->getTable(),
                'modelo' => get_class($event->model),
            ]);
        }

        if($tabla){
            $auditar = new AuditarDetalle();
            $auditar->user_id = auth()->user()->id ?? null;
            $auditar->name = auth()->user()->name ?? '';
            $auditar->email = auth()->user()->email ?? '';
            $auditar->auditar_tabla_id = $tabla->id;
            $auditar->nombre_modificado = ($event->model->name ?? $event->model->nombre ?? $event->model->nombres ?? '');
            // si solo se cambia el estado
            if( count($event->model->getDirty()) == 1 && $event->model->isDirty('estado') ){
                if( $event->model->estado == 1 )
                    $auditar->auditar_accion_id = 4; // activar
                else
                    $auditar->auditar_accion_id = 5; // desactivar
            }else{
                $auditar->auditar_accion_id = 2; // editar
            }

            $auditar->primary_key = $event->model->getKey();
            $auditar->despues = $event->model;
            $antes = \App::make( (string)get_class($event->model) )
                            ->where($event->model->getKeyName(), $event->model->getKey())->first();
            $auditar->antes = $antes;
            $auditar->save();
        }
    }
}
