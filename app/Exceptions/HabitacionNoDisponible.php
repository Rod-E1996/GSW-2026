<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Otra reserva o un bloqueo ocupo la habitacion durante el proceso de reservar.
 */
class HabitacionNoDisponible extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La habitación ya no está disponible para esas fechas.');
    }
}
