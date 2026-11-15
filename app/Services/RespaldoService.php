<?php

namespace App\Services;

use App\Models\Respaldo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Genera un respaldo completo de la base (estructura y datos) en un archivo .sql,
 * con PHP, sin depender de mysqldump. Se restaura con:  mysql -u usuario -p base < archivo.sql
 */
class RespaldoService
{
    const CARPETA = 'respaldos';
    const FILAS_POR_INSERT = 200;

    public function generar(?User $user = null, string $origen = Respaldo::MANUAL): Respaldo
    {
        $archivo = 'hotellink-' . now()->format('Ymd-His') . '.sql';
        Storage::disk('local')->makeDirectory(self::CARPETA);
        $ruta = Storage::disk('local')->path(self::CARPETA . '/' . $archivo);

        $this->volcar($ruta);

        return Respaldo::create([
            'archivo' => $archivo,
            'tamano' => filesize($ruta),
            'origen' => $origen,
            'user_id' => $user?->id,
        ]);
    }

    public function ruta(Respaldo $respaldo): string
    {
        return Storage::disk('local')->path(self::CARPETA . '/' . $respaldo->archivo);
    }

    private function volcar(string $ruta): void
    {
        $db = DB::connection();
        $mysql = in_array($db->getDriverName(), ['mysql', 'mariadb'], true);
        $pdo = $db->getPdo();
        $fh = fopen($ruta, 'w');

        fwrite($fh, "-- Respaldo de " . config('hotel.nombre') . " generado el " . now()->format('d/m/Y H:i') . "\n");
        if ($mysql) {
            fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
        }

        //Solo las tablas de esta base: sin el esquema, MySQL lista las de todas las bases del servidor
        $tablas = array_column(Schema::getTables($mysql ? $db->getDatabaseName() : null), 'name');

        foreach ($tablas as $tabla) {
            $crear = $mysql
                ? $db->selectOne("SHOW CREATE TABLE `$tabla`")->{'Create Table'}
                : $db->selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$tabla])->sql;

            fwrite($fh, "\nDROP TABLE IF EXISTS `$tabla`;\n$crear;\n");

            $lote = [];
            foreach ($db->table($tabla)->cursor() as $fila) {
                $fila = (array) $fila;
                $lote[] = '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $fila)) . ')';

                if (count($lote) === self::FILAS_POR_INSERT) {
                    $this->insertar($fh, $tabla, array_keys($fila), $lote);
                    $lote = [];
                }
            }
            if ($lote) {
                $this->insertar($fh, $tabla, array_keys($fila), $lote);
            }
        }

        if ($mysql) {
            fwrite($fh, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        }
        fclose($fh);
    }

    private function insertar($fh, string $tabla, array $columnas, array $lote): void
    {
        fwrite($fh, "INSERT INTO `$tabla` (`" . implode('`, `', $columnas) . "`) VALUES\n" . implode(",\n", $lote) . ";\n");
    }
}
