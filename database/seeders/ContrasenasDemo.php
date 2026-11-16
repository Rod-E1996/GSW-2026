<?php

namespace Database\Seeders;

/**
 * Contrasenas de las cuentas que crean los seeders: una por rol, todas de 8
 * caracteres y con el mismo patron (rol + "#26") para recordarlas facil.
 * Ser distintas por rol evita que la clave de un huesped de prueba abra el panel.
 *
 * Cambiarlas en produccion despues de sembrar.
 */
class ContrasenasDemo
{
    const ADMIN = 'Admin#26';       //Super Administrador
    const RECEPCION = 'Recep#26';   //Recepcionista
    const HUESPED = 'Guest#26';     //Huesped

    public static function paraRol(string $rol): string
    {
        return match ($rol) {
            'Super Administrador' => self::ADMIN,
            'Recepcionista' => self::RECEPCION,
            default => self::HUESPED,
        };
    }
}
