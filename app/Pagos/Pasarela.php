<?php

namespace App\Pagos;

use App\Models\Pago;

/**
 * Contrato de una pasarela de pago. El huesped paga en la pagina de la pasarela
 * (el hotel nunca guarda datos de la tarjeta) y la pasarela avisa el resultado
 * con una notificacion firmada. Conectar una pasarela real es agregar otra clase.
 */
interface Pasarela
{
    //Nombre que se guarda en pagos.pasarela
    public function nombre(): string;

    //URL de la pagina donde el huesped paga ese intento
    public function urlDePago(Pago $pago): string;

    /**
     * Valida la firma de una notificacion y devuelve sus datos normalizados:
     * referencia, aprobado (bool), monto (float) y respuesta (array completo).
     *
     * @throws NotificacionInvalida si la firma o el contenido no son validos
     */
    public function leerNotificacion(string $cuerpo, ?string $firma): array;
}
