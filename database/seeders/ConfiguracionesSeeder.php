<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

/**
 * Parametros de negocio del sistema. Los valores son provisionales hasta que el
 * hotel defina los suyos y se cambian desde el panel (Configuracion).
 * Es seguro re-ejecutarlo: nunca sobreescribe un valor ya guardado.
 */
class ConfiguracionesSeeder extends Seeder
{
    public static $parametros = [
        [
            'clave' => 'anticipo_porcentaje',
            'valor' => '30',
            'tipo' => Configuracion::PORCENTAJE,
            'grupo' => 'Reservas',
            'descripcion' => 'Porcentaje del total que se cobra como anticipo para confirmar la reserva.',
        ],
        [
            'clave' => 'impuesto_porcentaje',
            'valor' => '13',
            'tipo' => Configuracion::PORCENTAJE,
            'grupo' => 'Reservas',
            'descripcion' => 'Porcentaje de impuestos que se suma al alojamiento y a los servicios.',
        ],
        [
            'clave' => 'reserva_pendiente_horas',
            'valor' => '24',
            'tipo' => Configuracion::HORAS,
            'grupo' => 'Reservas',
            'descripcion' => 'Horas que una reserva puede seguir pendiente antes de vencer.',
        ],
        [
            'clave' => 'cancelacion_limite_horas',
            'valor' => '48',
            'tipo' => Configuracion::HORAS,
            'grupo' => 'Cancelación',
            'descripcion' => 'Horas antes de la llegada en que se puede cancelar con reembolso del anticipo.',
        ],
        [
            'clave' => 'cancelacion_reembolso_porcentaje',
            'valor' => '100',
            'tipo' => Configuracion::PORCENTAJE,
            'grupo' => 'Cancelación',
            'descripcion' => 'Porcentaje del anticipo que se devuelve al cancelar dentro del plazo.',
        ],
        [
            'clave' => 'no_show_reembolso_porcentaje',
            'valor' => '0',
            'tipo' => Configuracion::PORCENTAJE,
            'grupo' => 'Cancelación',
            'descripcion' => 'Porcentaje del anticipo que se devuelve si el huésped no se presenta.',
        ],
    ];

    public function run()
    {
        //La carga inicial no se audita: corre al migrar, antes de existir el catalogo de acciones
        Configuracion::withoutEvents(function () {
            foreach (self::$parametros as $parametro) {
                Configuracion::firstOrCreate(['clave' => $parametro['clave']], $parametro);
            }
        });
    }
}
