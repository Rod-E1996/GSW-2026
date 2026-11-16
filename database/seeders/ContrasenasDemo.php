<?php

namespace Database\Seeders;

/**
 * Contrasenas de las cuentas que crean los seeders. Todas las cuentas de
 * demostracion usan la misma clave (Demo12345) para facilitar la presentacion.
 *
 * Cambiarlas en produccion despues de sembrar.
 */
class ContrasenasDemo
{
    const DEMO = 'Demo12345';

    const ADMIN = self::DEMO;       //Super Administrador
    const RECEPCION = self::DEMO;   //Recepcionista
    const HUESPED = self::DEMO;     //Huesped

    public static function paraRol(string $rol): string
    {
        return match ($rol) {
            'Super Administrador' => self::ADMIN,
            'Recepcionista' => self::RECEPCION,
            default => self::HUESPED,
        };
    }
}
