<?php

/**
 * Esta prueba borra y vuelve a crear la base que usa. Se niega a correr
 * si no es MariaDB/MySQL o si el nombre de la base no termina en _concurrencia.
 */
function exigirBaseDeConcurrencia(): void
{
    $conexion = config('database.default');
    $driver = config("database.connections.$conexion.driver");
    $base = (string) config("database.connections.$conexion.database");

    if (!in_array($driver, ['mysql', 'mariadb']) || !str_ends_with($base, '_concurrencia')) {
        fwrite(STDERR, "Prueba cancelada: necesita una base MariaDB propia terminada en _concurrencia y apunta a \"$driver: $base\".\n");
        exit(2);
    }
}
