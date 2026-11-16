<?php

namespace Database\Seeders;

use App\Models\Reserva;
use App\Models\User;
use App\Models\Valoracion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Valoraciones de demostracion sobre estadias finalizadas de huespedes con cuenta
 * (solo ellos pueden valorar desde el portal). Corre despues de DemoHotelSeeder.
 *
 * - Las de las ultimas dos semanas quedan pendientes de moderar.
 * - Las anteriores estan aprobadas (algunas con respuesta del hotel) o rechazadas.
 * - La estadia finalizada de huesped@hotellink.com queda sin valorar, para
 *   poder probar el formulario desde "Mi cuenta".
 *
 * Solo corre si la tabla de valoraciones esta vacia.
 */
class ValoracionesSeeder extends Seeder
{
    const CUENTA_PRUEBA = 'huesped@hotellink.com';

    //Dias en que una valoracion recien escrita sigue esperando moderacion
    const DIAS_PENDIENTE = 14;

    private Carbon $ahora;

    public function run()
    {
        if (Valoracion::count() > 0) {
            $this->command?->warn('Ya hay valoraciones: se omite ValoracionesSeeder.');
            return;
        }

        mt_srand(2027);
        $this->ahora = Carbon::now();

        $moderadores = User::whereIn('email', ['gerencia@hotellink.com', 'admin@gmail.com'])->where('estado', 1)->get();
        if ($moderadores->isEmpty()) {
            $moderadores = User::role('Super Administrador')->where('estado', 1)->get();
        }

        $reservas = Reserva::with(['huesped.user'])
            ->where('estado', 1)
            ->where('estado_reserva', Reserva::FINALIZADA)
            ->whereNotNull('check_out_at')
            ->whereHas('huesped.user', fn ($q) => $q->where('email', '!=', self::CUENTA_PRUEBA))
            ->orderBy('check_out_at')
            ->get();

        foreach ($reservas as $reserva) {
            //No todos los huespedes se toman el tiempo de valorar
            if (mt_rand(1, 100) > 65) {
                continue;
            }

            $ingles = ($reserva->huesped->user->idioma ?? 'es') === 'en';
            $puntuacion = $this->elegir([5 => 46, 4 => 32, 3 => 12, 2 => 6, 1 => 4]);

            //La escribe entre unas horas y cuatro dias despues de salir
            $creada = $reserva->check_out_at->copy()->addMinutes(mt_rand(180, 4 * 24 * 60));
            if ($creada->gt($this->ahora)) {
                $creada = $this->ahora->copy()->subMinutes(mt_rand(10, 240));
            }

            $estado = Valoracion::PENDIENTE;
            $comentario = mt_rand(1, 100) <= 85 ? $this->comentario($puntuacion, $ingles) : null;
            $respuesta = null;
            $moderada = null;
            $moderador = null;

            if ($creada->lt($this->ahora->copy()->subDays(self::DIAS_PENDIENTE))) {
                $moderada = $creada->copy()->addMinutes(mt_rand(120, 36 * 60));
                $moderador = $moderadores->isNotEmpty() ? $moderadores[mt_rand(0, $moderadores->count() - 1)] : null;

                if (mt_rand(1, 100) <= 6) {
                    //Rechazadas: publicidad o texto que no habla de la estadia
                    $estado = Valoracion::RECHAZADA;
                    $comentario = $this->comentarioRechazado();
                } else {
                    $estado = Valoracion::APROBADA;
                    //Las criticas siempre se responden; las buenas, a veces
                    if ($comentario && ($puntuacion <= 3 || mt_rand(1, 100) <= 45)) {
                        $respuesta = $this->respuesta($puntuacion, $ingles, $reserva->huesped->nombres);
                    }
                }
            }

            $valoracion = new Valoracion([
                'reserva_id' => $reserva->id,
                'huesped_id' => $reserva->huesped_id,
                'puntuacion' => $puntuacion,
                'comentario' => $comentario,
                'estado' => $estado,
                'respuesta' => $respuesta,
                'moderada_at' => $moderada,
                'user_id' => $moderador?->id,
            ]);
            $valoracion->created_at = $creada;
            $valoracion->updated_at = $moderada ?? $creada;
            $valoracion->save();
        }

        $this->command?->info('Valoraciones: ' . Valoracion::count() . ' ('
            . Valoracion::where('estado', Valoracion::PENDIENTE)->count() . ' pendientes, promedio publicado '
            . round((float) Valoracion::where('estado', Valoracion::APROBADA)->avg('puntuacion'), 1) . ').');
    }

    private function comentario(int $puntuacion, bool $ingles): string
    {
        $es = [
            5 => [
                'Todo excelente. La habitación impecable y el personal de recepción muy atento.',
                'Volveremos sin duda. El desayuno muy bueno y la vista desde la habitación, increíble.',
                'Celebramos nuestro aniversario y nos prepararon un detalle en la habitación. ¡Gracias!',
                'Muy limpio, cama cómoda y silencioso por la noche. Perfecto para descansar.',
                'La atención de Sofía y Carlos en recepción fue de diez. Nos ayudaron con el tour y el transporte.',
                'Fuimos en familia y los niños disfrutaron muchísimo de las actividades. Muy recomendado.',
                'Excelente relación calidad-precio. El proceso de reserva en línea fue rápido y sencillo.',
            ],
            4 => [
                'Muy buena estadía. Solo mejoraría la presión del agua en la ducha.',
                'Buen hotel y bien ubicado. El desayuno podría tener más variedad.',
                'Habitación cómoda y limpia. El wifi se cortó un par de veces, por lo demás todo bien.',
                'Personal amable y buena comida. El estacionamiento se llena los fines de semana.',
                'Nos gustó mucho. La entrada tardó un poco porque había varias llegadas a la vez.',
            ],
            3 => [
                'Correcto, sin más. La habitación necesitaba algo de mantenimiento en el baño.',
                'El aire acondicionado hacía bastante ruido por la noche. La atención fue buena.',
                'Bien en general, pero esperábamos más por el precio en temporada alta.',
            ],
            2 => [
                'La habitación no estaba lista a la hora de entrada y tuvimos que esperar casi una hora.',
                'Hubo ruido de una fiesta hasta tarde y nadie hizo nada. El personal, amable.',
            ],
            1 => [
                'Mala experiencia: problemas con el agua caliente durante toda la estadía.',
                'Nos cobraron un servicio que no pedimos y costó mucho que lo corrigieran.',
            ],
        ];

        $en = [
            5 => [
                'Amazing stay! Spotless room and the front desk staff were incredibly helpful.',
                'Beautiful views, great breakfast and a very relaxing atmosphere. We will be back.',
                'The surf lessons and the beach dinner were the highlight of our trip.',
                'Super easy online booking and a smooth check-in. Highly recommended.',
            ],
            4 => [
                'Great hotel overall. The wifi was a bit slow in the evenings.',
                'Lovely staff and comfortable beds. Breakfast could use more options.',
                'Very good value. Check-in took a while because several guests arrived at once.',
            ],
            3 => [
                'Decent stay, but the air conditioning was noisy at night.',
                'Good location, although the room felt a bit dated.',
            ],
            2 => [
                'Our room was not ready at check-in time and we waited almost an hour.',
            ],
            1 => [
                'No hot water for most of our stay. Disappointing.',
            ],
        ];

        $lista = ($ingles ? $en : $es)[$puntuacion];

        return $lista[mt_rand(0, count($lista) - 1)];
    }

    private function comentarioRechazado(): string
    {
        $lista = [
            '¡Visita mi tienda en línea y obtén 20 % de descuento en tu próxima compra!',
            'asdfgh prueba prueba',
            'Busco trabajo de recepcionista, ¿a quién le envío mi hoja de vida?',
        ];

        return $lista[mt_rand(0, count($lista) - 1)];
    }

    private function respuesta(int $puntuacion, bool $ingles, string $nombre): string
    {
        $nombre = explode(' ', $nombre)[0];

        if ($ingles) {
            return $puntuacion >= 4
                ? "Thank you, $nombre! We are delighted you enjoyed your stay and hope to welcome you back soon."
                : "Dear $nombre, we are sorry your stay did not meet expectations. We have shared your comments with our maintenance team so it does not happen again.";
        }

        $buenas = [
            "¡Muchas gracias, $nombre! Nos alegra que haya disfrutado su estadía. Lo esperamos pronto.",
            "Gracias por su comentario, $nombre. Le compartimos sus palabras al equipo de recepción.",
            "Gracias por elegirnos, $nombre. Tomamos nota de su sugerencia para seguir mejorando.",
        ];
        $malas = [
            "Estimado(a) $nombre, lamentamos los inconvenientes. Ya revisamos el problema con el equipo de mantenimiento.",
            "$nombre, gracias por avisarnos. Hemos reforzado la preparación de habitaciones para que no vuelva a ocurrir.",
        ];

        $lista = $puntuacion >= 4 ? $buenas : $malas;

        return $lista[mt_rand(0, count($lista) - 1)];
    }

    //Elige una clave segun su peso: [5 => 46, 4 => 32]
    private function elegir(array $pesos)
    {
        $r = mt_rand(1, array_sum($pesos));
        foreach ($pesos as $valor => $peso) {
            if (($r -= $peso) <= 0) {
                return $valor;
            }
        }

        return array_key_first($pesos);
    }
}
