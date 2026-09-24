<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

use App\Events\SaveEvent;
use App\Models\AuditarDetalle;
use App\Models\AuditarTabla;

class SaveListener
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
    public function handle( SaveEvent $event)
    {
        $tabla = AuditarTabla::query();// obtener la tabla que se esta editando
        $tabla->where('modelo', get_class($event->model) );
        $tabla = $tabla->first();

        if( !$tabla ){
            $tabla = AuditarTabla::create([
                'tabla' =>  $event->model->getTable(),
                'modelo' => get_class($event->model),
            ]);
        }

        if( $tabla ){
            $auditar = new AuditarDetalle();
            $auditar->user_id = auth()->user()->id ?? null;
            $auditar->name = auth()->user()->name  ?? '';
            $auditar->email = auth()->user()->email ?? '';
            $auditar->auditar_tabla_id = $tabla->id;
            $auditar->nombre_modificado = ($event->model->name ?? $event->model->nombre ?? $event->model->nombres ?? '');
            // si solo se cambia el estado

            $auditar->auditar_accion_id = 1; // agregar

            $auditar->primary_key = $event->model->getKey();
            $auditar->despues = $event->model;
            $auditar->antes = null;
            $auditar->save();
        }
    }
}
