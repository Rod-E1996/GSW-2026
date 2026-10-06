<?php

namespace App\Traits;

use Illuminate\Http\RedirectResponse;

/**
 * Helpers compartidos por los controladores CRUD del panel.
 * Centraliza los mensajes de alerta y la respuesta de "registro no encontrado",
 * que antes se repetian identicos en cada controlador.
 */
trait RespuestasCrud
{
    //Redireccion con alerta de exito (color por defecto del sistema)
    protected function exito(string $ruta, string $mensaje): RedirectResponse
    {
        return redirect()->route($ruta)->with('alerta', $mensaje);
    }

    //Redireccion con alerta de error
    protected function error(string $ruta, string $mensaje): RedirectResponse
    {
        return redirect()->route($ruta)->with([
            'alerta' => $mensaje,
            'tipo' => 'error',
        ]);
    }

    //Vuelve a la pagina anterior con alerta de error (para acciones sobre la misma vista)
    protected function errorAtras(string $mensaje): RedirectResponse
    {
        return back()->with([
            'alerta' => $mensaje,
            'tipo' => 'error',
        ]);
    }
}
