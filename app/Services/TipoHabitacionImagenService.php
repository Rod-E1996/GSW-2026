<?php

namespace App\Services;

use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;

/**
 * Logica de las fotos de un tipo de habitacion (antes vivia en el controlador).
 * Mantenerla aqui deja el controlador delgado y permite reutilizar/probar esta
 * logica de forma aislada.
 */
class TipoHabitacionImagenService
{
    //Guarda en disco las imagenes subidas y crea sus registros.
    //La primera imagen del tipo queda como principal automaticamente.
    public function guardar(TipoHabitacion $tipoHabitacion, array $archivos): void
    {
        if (empty($archivos)) {
            return;
        }

        $tienePrincipal = $tipoHabitacion->imagenes()->where('principal', true)->exists();
        $orden = (int) $tipoHabitacion->imagenes()->max('orden');

        foreach ($archivos as $archivo) {
            if (!$archivo || !$archivo->isValid()) {
                continue;
            }

            $ruta = $archivo->store(
                TipoHabitacionImagen::CARPETA . '/' . $tipoHabitacion->id,
                TipoHabitacionImagen::DISCO
            );
            $orden++;

            TipoHabitacionImagen::create([
                'tipo_habitacion_id' => $tipoHabitacion->id,
                'ruta' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'orden' => $orden,
                'principal' => !$tienePrincipal,
            ]);

            $tienePrincipal = true;
        }
    }

    //Elimina una imagen (archivo + registro). Si era la principal, promueve la siguiente.
    public function eliminar(TipoHabitacionImagen $imagen): void
    {
        $eraPrincipal = $imagen->principal;
        $tipoId = $imagen->tipo_habitacion_id;

        $imagen->eliminarArchivo();
        $imagen->delete();

        if ($eraPrincipal) {
            $siguiente = TipoHabitacionImagen::where('tipo_habitacion_id', $tipoId)
                ->orderBy('orden')
                ->orderBy('id')
                ->first();

            if ($siguiente) {
                $siguiente->principal = true;
                $siguiente->save();
            }
        }
    }

    //Marca una imagen como principal y quita la marca de las demas del mismo tipo.
    public function marcarPrincipal(TipoHabitacionImagen $imagen): void
    {
        TipoHabitacionImagen::where('tipo_habitacion_id', $imagen->tipo_habitacion_id)
            ->where('principal', true)
            ->update(['principal' => false]);

        $imagen->principal = true;
        $imagen->save();
    }
}
