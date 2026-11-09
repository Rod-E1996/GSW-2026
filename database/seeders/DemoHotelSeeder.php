<?php

namespace Database\Seeders;

use App\Models\BloqueoHabitacion;
use App\Models\Comprobante;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\ReservaHistorial;
use App\Models\Servicio;
use App\Models\Tarifa;
use App\Models\Temporada;
use App\Models\TipoHabitacion;
use App\Models\User;
use App\Services\CotizacionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Datos de demostracion: personal, huespedes con y sin cuenta, tarifas especiales,
 * bloqueos y unos seis meses de reservas (pasadas, en curso y futuras) con su
 * historial, pagos, comprobantes y correos enviados.
 *
 * Las fechas son relativas al dia en que se ejecuta, asi que la demo siempre
 * tiene huespedes en casa, llegadas y salidas del dia y reservas por venir.
 * Nunca asigna una habitacion ocupada o bloqueada: lleva un mapa de ocupacion.
 *
 * Se ejecuta con eventos de modelo apagados: el historial y las fechas se escriben
 * a mano para que parezcan reales y la auditoria no se llena con la carga inicial.
 * Solo corre si la tabla de reservas esta vacia.
 *
 * Todas las cuentas usan la contrasena 12345678.
 */
class DemoHotelSeeder extends Seeder
{
    const PASSWORD = '12345678';

    //Dias de historia y de reservas futuras alrededor de hoy
    const DIAS_ATRAS = 180;
    const DIAS_ADELANTE = 120;

    private CotizacionService $cotizaciones;
    private Carbon $hoy;
    private Carbon $ahora;

    private array $staff = [];          //Recepcionistas activos
    private ?User $gerencia = null;
    private array $huespedesCuenta = [];  //[Huesped con user cargado]
    private ?Huesped $huespedPrueba = null;
    private array $huespedesSinCuenta = [];
    private $tipos;                     //TipoHabitacion por id
    private $habitaciones;              //Habitacion activas
    private $servicios;                 //Servicio por nombre
    private array $ocupacion = [];      //[habitacion_id][Y-m-d] => true
    private array $salenHoy = [];       //habitaciones liberadas esta manana

    public function run()
    {
        if (Reserva::count() > 0) {
            $this->command?->warn('Ya hay reservas: se omite DemoHotelSeeder.');
            return;
        }

        mt_srand(2026);
        $this->cotizaciones = app(CotizacionService::class);
        $this->hoy = Carbon::today();
        $this->ahora = Carbon::now();

        Model::withoutEvents(function () {
            DB::transaction(function () {
                $this->crearPersonal();
                $this->crearHuespedes();
                $this->cargarCatalogos();
                $this->crearTarifas();
                $this->crearBloqueos();
                $this->crearReservasDestacadas();
                $this->crearReservasAleatorias();
                $this->actualizarEstadoHabitaciones();
            });
        });

        $this->command?->info('Demo: ' . Reserva::count() . ' reservas, ' . Huesped::count() . ' huéspedes, '
            . Pago::count() . ' pagos y ' . User::count() . ' usuarios.');
    }

    // ------------------------------------------------------------------
    // Personas
    // ------------------------------------------------------------------

    private function crearPersonal(): void
    {
        $this->gerencia = $this->usuario('Mariela Fuentes', 'gerencia@hotellink.com', '+503 7811-2040', 'Super Administrador');

        $this->staff[] = User::where('email', 'recepcion@hotellink.com')->first();
        $this->staff[] = $this->usuario('Carlos Portillo', 'carlos.portillo@hotellink.com', '+503 7811-2041', 'Recepcionista');
        $this->staff[] = $this->usuario('Sofía Henríquez', 'sofia.henriquez@hotellink.com', '+503 7811-2042', 'Recepcionista', 'en');
        $this->staff = array_values(array_filter($this->staff));

        //Exempleado: la cuenta existe pero esta desactivada
        $this->usuario('Diego Alvarado', 'diego.alvarado@hotellink.com', '+503 7811-2043', 'Recepcionista', 'es', 0);
    }

    private function crearHuespedes(): void
    {
        //[nombres, apellidos, nacionalidad, tipo doc, idioma, notas]
        $conCuenta = [
            ['Gabriela', 'Martínez Rosales', 'El Salvador', 'DUI', 'es', 'Cliente frecuente. Prefiere piso alto.'],
            ['José Roberto', 'Hernández', 'El Salvador', 'DUI', 'es', null],
            ['Valeria', 'Castro Mendoza', 'El Salvador', 'DUI', 'es', 'Alergia a los mariscos.'],
            ['Luis Fernando', 'Quintanilla', 'El Salvador', 'DUI', 'es', null],
            ['Daniela', 'Orellana', 'El Salvador', 'DUI', 'es', null],
            ['Ricardo', 'Molina Paz', 'Guatemala', 'Pasaporte', 'es', null],
            ['Ana Lucía', 'Barrios', 'Guatemala', 'Pasaporte', 'es', 'Viaja con mascota pequeña (consultar política).'],
            ['Marco', 'Zelaya', 'Honduras', 'Pasaporte', 'es', null],
            ['Fernanda', 'Solís Vargas', 'Costa Rica', 'Pasaporte', 'es', null],
            ['Alejandro', 'Ruiz Treviño', 'México', 'Pasaporte', 'es', 'Celebra aniversario en diciembre.'],
            ['Lucía', 'Fernández Ortega', 'España', 'Pasaporte', 'es', null],
            ['Emily', 'Johnson', 'Estados Unidos', 'Pasaporte', 'en', 'Surfista, pregunta por clases avanzadas.'],
            ['Michael', 'Thompson', 'Estados Unidos', 'Pasaporte', 'en', null],
            ['Sarah', 'Williams', 'Estados Unidos', 'Pasaporte', 'en', 'Vegetariana.'],
            ['David', 'Tremblay', 'Canadá', 'Pasaporte', 'en', null],
            ['Chloé', 'Gagnon', 'Canadá', 'Pasaporte', 'en', null],
            ['Kevin', 'Ayala', 'El Salvador', 'DUI', 'es', null],
            ['Paola', 'Guevara Linares', 'El Salvador', 'DUI', 'es', 'Solicita factura a nombre de empresa.'],
            ['Óscar', 'Chávez', 'El Salvador', 'DUI', 'es', null],
            ['Natalia', 'Romero', 'Colombia', 'Pasaporte', 'es', null],
        ];

        //La cuenta de prueba del rol Huesped (RolesHotelSeeder) tambien recibe datos completos
        $prueba = User::where('email', 'huesped@hotellink.com')->first();
        if ($prueba) {
            $ficha = Huesped::paraUsuario($prueba);
            $ficha->update($this->datosFicha('El Salvador', 'DUI', 1) + ['nombres' => 'Huésped', 'apellidos' => 'de Prueba']);
            $prueba->forceFill(['phone' => $ficha->telefono, 'privacidad_aceptada_at' => $prueba->created_at])->save();
            //Fuera del sorteo: su "Mi cuenta" muestra solo los casos de crearReservasDestacadas
            $this->huespedPrueba = $ficha->setRelation('user', $prueba);
        }

        foreach ($conCuenta as $i => [$nombres, $apellidos, $pais, $doc, $idioma, $notas]) {
            $correo = $this->correo($nombres, $apellidos);
            $datos = $this->datosFicha($pais, $doc, $i + 10);

            //Una cuenta recien creada que aun no confirma su correo
            $verificada = $i !== count($conCuenta) - 1;

            $user = $this->usuario("$nombres $apellidos", $correo, $datos['telefono'], 'Huésped', $idioma, 1, $verificada);
            $ficha = Huesped::paraUsuario($user, ['nombres' => $nombres, 'apellidos' => $apellidos] + $datos + ['notas' => $notas]);

            if ($verificada) {
                $this->huespedesCuenta[] = $ficha->setRelation('user', $user);
            }
        }

        //Huespedes atendidos por telefono o en recepcion, sin cuenta en el portal
        $sinCuenta = [
            ['Carmen Elena', 'López'], ['Francisco', 'Rivera Díaz'], ['Rosa María', 'Aguilar'], ['Jorge', 'Mejía Campos'],
            ['Patricia', 'Flores'], ['Manuel', 'Ramírez Alas'], ['Claudia', 'Escobar'], ['Roberto', 'Cruz Menjívar'],
            ['Silvia', 'Pineda'], ['Eduardo', 'Morales'], ['Karla', 'Vásquez Durán'], ['Héctor', 'Bonilla'],
            ['Andrea', 'Mejía'], ['Familia', 'Rivas'], ['Mauricio', 'Castaneda'], ['Beatriz', 'Sandoval'],
            ['William', 'Funes'], ['Lorena', 'Recinos'], ['Samuel', 'Argueta'], ['Mónica', 'Palacios'],
            ['Tomás', 'Herrera'], ['Gloria', 'Cañas'], ['Iván', 'Portillo'], ['Ruth', 'Melara'],
            ['Brian', 'Miller'], ['Jessica', 'Davis'], ['Thomas', 'Wilson'], ['Laura', 'Schmidt'],
            ['Pablo', 'García Núñez'], ['Mariana', 'Ochoa'], ['Erick', 'Batres'], ['Jennifer', 'Alfaro'],
            ['Nelson', 'Umaña'], ['Teresa', 'Galdámez'], ['Cristian', 'Moreno'], ['Elsa', 'Turcios'],
        ];
        $paises = ['El Salvador', 'El Salvador', 'El Salvador', 'El Salvador', 'Guatemala', 'Honduras', 'Estados Unidos', 'México'];

        foreach ($sinCuenta as $i => [$nombres, $apellidos]) {
            $pais = in_array($nombres, ['Brian', 'Jessica', 'Thomas']) ? 'Estados Unidos'
                : ($nombres === 'Laura' ? 'Alemania' : $paises[$i % count($paises)]);
            $doc = $pais === 'El Salvador' ? 'DUI' : 'Pasaporte';

            $this->huespedesSinCuenta[] = Huesped::create(['nombres' => $nombres, 'apellidos' => $apellidos]
                + $this->datosFicha($pais, $doc, $i + 100)
                + ['email' => $i % 4 === 3 ? null : $this->correo($nombres, $apellidos)]);
        }
    }

    private function usuario(string $nombre, string $correo, ?string $telefono, string $rol, string $idioma = 'es', int $estado = 1, bool $verificada = true): User
    {
        $creada = $this->hoy->copy()->subDays(mt_rand(30, 400))->setTime(mt_rand(8, 20), mt_rand(0, 59));

        $user = User::firstOrNew(['email' => $correo]);
        $user->fill([
            'name' => $nombre,
            'phone' => $telefono,
            'password' => Hash::make(self::PASSWORD),
            'idioma' => $idioma,
            'estado' => $estado,
        ]);
        $user->email_verified_at = $verificada ? $creada->copy()->addMinutes(mt_rand(2, 90)) : null;
        $user->privacidad_aceptada_at = $creada;
        $user->created_at = $creada;
        $user->save();

        if (!$user->hasRole($rol)) {
            $user->assignRole($rol);
        }

        return $user;
    }

    private function datosFicha(string $pais, string $tipoDoc, int $semilla): array
    {
        $calles = ['Col. Escalón, Calle El Mirador', 'Res. Santa Elena, Antiguo Cuscatlán', 'Col. San Benito, Av. Las Magnolias',
            'Barrio El Centro, Santa Ana', 'Col. Miramonte, San Salvador', 'Urb. Madre Selva, Santa Tecla', 'Zona 10, Ciudad de Guatemala',
            'Col. Palmira, Tegucigalpa', 'Escazú, San José', 'Polanco, Ciudad de México', 'Chamberí, Madrid', 'Austin, Texas', 'Montreal, Quebec'];

        $prefijo = match ($pais) {
            'El Salvador' => '+503 7',
            'Guatemala' => '+502 5',
            'Honduras' => '+504 9',
            'Costa Rica' => '+506 8',
            'México' => '+52 55 ',
            'Colombia' => '+57 31',
            'España' => '+34 6',
            'Alemania' => '+49 15',
            'Canadá' => '+1 514 ',
            default => '+1 512 ',
        };

        return [
            'tipo_documento' => $tipoDoc,
            'documento' => $tipoDoc === 'DUI'
                ? sprintf('%08d-%d', 3000000 + $semilla * 7919 % 6000000, $semilla % 10)
                : strtoupper(substr($pais, 0, 1)) . sprintf('%08d', 41000000 + $semilla * 3571),
            'telefono' => $prefijo . sprintf('%03d-%04d', 100 + $semilla * 37 % 900, 1000 + $semilla * 613 % 9000),
            'nacionalidad' => $pais,
            'fecha_nacimiento' => $this->hoy->copy()->subYears(mt_rand(22, 68))->subDays(mt_rand(0, 364))->toDateString(),
            'direccion' => $calles[$semilla % count($calles)],
        ];
    }

    //Correos en example.com: dominio reservado, ningun correo de la demo llega a una persona real
    private function correo(string $nombres, string $apellidos): string
    {
        return Str::slug(explode(' ', $nombres)[0] . '.' . explode(' ', $apellidos)[0], '.') . '@example.com';
    }

    // ------------------------------------------------------------------
    // Catalogos
    // ------------------------------------------------------------------

    private function cargarCatalogos(): void
    {
        $this->tipos = TipoHabitacion::where('estado', 1)->get()->keyBy('id');
        $this->habitaciones = Habitacion::where('estado', 1)->orderBy('numero')->get();
        $this->servicios = Servicio::where('estado', 1)->get()->keyBy('nombre');
    }

    //Precios fijos por temporada para algunos tipos (en vez del multiplicador)
    private function crearTarifas(): void
    {
        $tipo = fn (string $nombre) => $this->tipos->firstWhere('nombre', $nombre);

        $tarifas = [
            ['Suite', 'Fin de año 2026', 180.00],
            ['Suite', 'Semana Santa 2027', 175.00],
            ['Familiar', 'Fiestas agostinas 2026', 115.00],
            ['Familiar', 'Fiestas agostinas 2027', 120.00],
            ['Familiar', 'Fin de año 2026', 125.00],
        ];

        foreach ($tarifas as [$nombreTipo, $nombreTemporada, $precio]) {
            $temporada = Temporada::where('nombre', $nombreTemporada)->first();
            if ($tipo($nombreTipo) && $temporada) {
                Tarifa::firstOrCreate(
                    ['tipo_habitacion_id' => $tipo($nombreTipo)->id, 'temporada_id' => $temporada->id],
                    ['precio_noche' => $precio]
                );
            }
        }
    }

    private function crearBloqueos(): void
    {
        //[habitacion, desde (dias desde hoy), hasta, motivo, descripcion]
        $bloqueos = [
            ['104', -3, 4, BloqueoHabitacion::MANTENIMIENTO, 'Cambio del compresor del aire acondicionado.'],
            ['203', -60, -55, BloqueoHabitacion::MANTENIMIENTO, 'Pintura y cambio de cortinas.'],
            ['302', 20, 22, BloqueoHabitacion::USO_INTERNO, 'Sesión de fotos para la nueva página web.'],
            ['401', 45, 47, BloqueoHabitacion::USO_INTERNO, 'Visita de inspección de la cadena.'],
            ['105', 9, 10, BloqueoHabitacion::MANTENIMIENTO, 'Revisión de fontanería del baño.'],
        ];

        foreach ($bloqueos as [$numero, $desde, $hasta, $motivo, $descripcion]) {
            $habitacion = $this->habitaciones->firstWhere('numero', $numero);
            if (!$habitacion) {
                continue;
            }

            $inicio = $this->hoy->copy()->addDays($desde);
            $fin = $this->hoy->copy()->addDays($hasta);

            $bloqueo = BloqueoHabitacion::create([
                'habitacion_id' => $habitacion->id,
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $fin->toDateString(),
                'motivo' => $motivo,
                'descripcion' => $descripcion,
                'user_id' => $this->gerencia?->id,
            ]);
            $bloqueo->created_at = $inicio->copy()->subDays(mt_rand(3, 15))->setTime(9, 30);
            $bloqueo->save();

            for ($d = $inicio->copy(); $d->lte($fin); $d->addDay()) {
                $this->ocupacion[$habitacion->id][$d->toDateString()] = true;
            }
        }
    }

    // ------------------------------------------------------------------
    // Reservas
    // ------------------------------------------------------------------

    //Casos fijos para que la cuenta de prueba y la recepcion tengan algo que mostrar
    private function crearReservasDestacadas(): void
    {
        $prueba = $this->huespedPrueba;
        if ($prueba) {
            $this->reservar($prueba, 'Doble', -40, 3, Reserva::FINALIZADA, Reserva::CANAL_WEB, 2, 0, ['Alimentación']);
            $this->reservar($prueba, 'Suite', 12, 4, Reserva::CONFIRMADA, Reserva::CANAL_WEB, 2, 0, ['Masaje relajante', 'Cena romántica en la playa']);
            $this->reservar($prueba, 'Sencilla', 30, 2, Reserva::PENDIENTE, Reserva::CANAL_WEB, 1, 0);
            $this->reservar($prueba, 'Familiar', -95, 2, Reserva::CANCELADA, Reserva::CANAL_WEB, 2, 2);
        }

        //Movimiento del dia en recepcion: llegadas por registrar y salidas por cobrar
        $this->reservar($this->huespedSinCuenta(), 'Doble', 0, 3, Reserva::CONFIRMADA, Reserva::CANAL_TELEFONO, 2, 0);
        $this->reservar($this->huespedConCuenta(), 'Familiar', 0, 4, Reserva::CONFIRMADA, Reserva::CANAL_WEB, 2, 2, ['Actividades recreativas']);
        $this->reservar($this->huespedConCuenta(), 'Suite', -3, 3, Reserva::EN_ESTADIA, Reserva::CANAL_WEB, 2, 0, ['Alimentación']);
        $this->reservar($this->huespedSinCuenta(), 'Sencilla', -2, 2, Reserva::EN_ESTADIA, Reserva::CANAL_PRESENCIAL, 1, 0, ['Lavandería']);
    }

    private function crearReservasAleatorias(): void
    {
        $inicio = $this->hoy->copy()->subDays(self::DIAS_ATRAS);

        for ($dia = 0; $dia <= self::DIAS_ATRAS + self::DIAS_ADELANTE; $dia++) {
            $fecha = $inicio->copy()->addDays($dia);
            $relativo = $dia - self::DIAS_ATRAS;

            //Mas llegadas en fin de semana y temporada; menos cuanto mas lejos en el futuro
            $llegadas = mt_rand(0, 3);
            if ($fecha->isFriday() || $fecha->isSaturday()) {
                $llegadas += 2;
            }
            if (Temporada::vigentePara($fecha->toDateString())) {
                $llegadas += 2;
            }
            if ($relativo > 60) {
                $llegadas = (int) floor($llegadas / 3);
            } elseif ($relativo > 21) {
                $llegadas = (int) floor($llegadas / 2);
            }

            for ($i = 0; $i < $llegadas; $i++) {
                $this->reservaAleatoria($relativo);
            }
        }
    }

    private function reservaAleatoria(int $relativo): void
    {
        $noches = $this->elegir([1 => 15, 2 => 30, 3 => 25, 4 => 12, 5 => 8, 7 => 6, 10 => 4]);
        [$adultos, $ninos, $tipo] = $this->elegirGrupo();

        $canal = $this->elegir([Reserva::CANAL_WEB => 55, Reserva::CANAL_TELEFONO => 25, Reserva::CANAL_PRESENCIAL => 20]);
        if ($relativo > 0 && $canal === Reserva::CANAL_PRESENCIAL) {
            $canal = Reserva::CANAL_TELEFONO;   //En persona solo se reserva para hoy
        }
        $huesped = $canal === Reserva::CANAL_WEB ? $this->huespedConCuenta() : $this->huespedSinCuenta();

        $servicios = [];
        if (mt_rand(1, 100) <= 40) {
            $servicios = (array) array_rand(array_flip($this->serviciosPara($tipo)), mt_rand(1, 2));
        }

        $this->reservar($huesped, $tipo, $relativo, $noches, $this->estadoSegunFechas($relativo, $noches), $canal, $adultos, $ninos, $servicios);
    }

    private function estadoSegunFechas(int $llegada, int $noches): int
    {
        $salida = $llegada + $noches;
        $r = mt_rand(1, 100);

        if ($salida < 0) {
            return $r <= 86 ? Reserva::FINALIZADA : Reserva::CANCELADA;
        }
        if ($salida === 0) {
            return $r <= 55 ? Reserva::EN_ESTADIA : ($r <= 90 ? Reserva::FINALIZADA : Reserva::CANCELADA);
        }
        if ($llegada < 0) {
            return Reserva::EN_ESTADIA;
        }
        if ($llegada === 0) {
            return $r <= 50 ? Reserva::CONFIRMADA : Reserva::EN_ESTADIA;
        }

        return $r <= 72 ? Reserva::CONFIRMADA : ($r <= 87 ? Reserva::PENDIENTE : Reserva::CANCELADA);
    }

    /**
     * Crea una reserva completa. $llegada es en dias desde hoy.
     * Devuelve null si no queda habitacion libre de ese tipo.
     */
    private function reservar(Huesped $huesped, string $nombreTipo, int $llegada, int $noches, int $estado, string $canal,
                              int $adultos, int $ninos, array $nombresServicios = []): ?Reserva
    {
        $tipo = $this->tipos->firstWhere('nombre', $nombreTipo);
        if (!$tipo) {
            return null;
        }

        $fechaLlegada = $this->hoy->copy()->addDays($llegada);
        $fechaSalida = $fechaLlegada->copy()->addDays($noches);
        $ocupa = !in_array($estado, [Reserva::CANCELADA]);

        $habitacion = $this->habitacionLibre($tipo->id, $fechaLlegada, $noches, $llegada >= 0);
        if (!$habitacion) {
            return null;
        }

        $recepcion = $this->staff[mt_rand(0, count($this->staff) - 1)];
        $cuenta = $canal === Reserva::CANAL_WEB ? $huesped->user : null;

        //Servicios: los consumidos en la estadia se cargan como pedidos para que los importes cuadren
        $pedidos = [];
        foreach ($nombresServicios as $nombre) {
            $servicio = $this->servicios->get($nombre);
            if ($servicio) {
                $pedidos[] = [
                    'servicio_id' => $servicio->id,
                    'cantidad' => 1,
                    'fecha_servicio' => $fechaLlegada->copy()->addDays(mt_rand(0, max(0, $noches - 1)))->toDateString(),
                    'hora_servicio' => sprintf('%02d:00', mt_rand(9, 19)),
                ];
            }
        }

        $cotizacion = $this->cotizaciones->calcular($tipo, $fechaLlegada->toDateString(), $fechaSalida->toDateString(), $adultos + $ninos, $pedidos);

        //Momento en que se hizo la reserva
        $creada = $this->momentoCreacion($fechaLlegada, $canal, $estado);

        $reserva = new Reserva([
            'codigo' => Reserva::generarCodigo(),
            'huesped_id' => $huesped->id,
            'tipo_habitacion_id' => $tipo->id,
            'habitacion_id' => $habitacion->id,
            'fecha_llegada' => $fechaLlegada->toDateString(),
            'fecha_salida' => $fechaSalida->toDateString(),
            'adultos' => $adultos,
            'ninos' => $ninos,
            'hora_estimada_llegada' => mt_rand(1, 100) <= 45 ? sprintf('%02d:%s', mt_rand(13, 20), mt_rand(0, 1) ? '00' : '30') : null,
            'canal' => $canal,
            'estado_reserva' => $estado,
            'subtotal_alojamiento' => $cotizacion['subtotal_alojamiento'],
            'subtotal_servicios' => $cotizacion['subtotal_servicios'],
            'descuento' => 0,
            'impuesto' => $cotizacion['impuesto'],
            'total' => $cotizacion['total'],
            'anticipo_requerido' => $cotizacion['anticipo_requerido'],
            'notas' => mt_rand(1, 100) <= 18 ? $this->notaReserva() : null,
            'user_id' => $cuenta?->id ?? $recepcion->id,
        ]);
        $reserva->created_at = $creada;
        $reserva->updated_at = $creada;
        $reserva->save();

        foreach ($cotizacion['noches'] as $noche) {
            $reserva->noches()->create([
                'habitacion_id' => $habitacion->id,
                'fecha' => $noche['fecha'],
                'temporada_id' => $noche['temporada_id'],
                'precio_noche' => $noche['precio_noche'],
            ]);
            if ($ocupa) {
                $this->ocupacion[$habitacion->id][$noche['fecha']] = true;
            }
        }

        foreach ($cotizacion['servicios'] as $linea) {
            //Los servicios de lavanderia y alimentacion en estadias ya iniciadas cuentan como consumidos
            $consumido = in_array($estado, [Reserva::EN_ESTADIA, Reserva::FINALIZADA]) && mt_rand(0, 1);
            $reserva->servicios()->attach($linea['servicio_id'], [
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'subtotal' => $linea['subtotal'],
                'fecha_servicio' => $linea['fecha_servicio'],
                'hora_servicio' => $linea['hora_servicio'],
                'estado_servicio' => $consumido ? Reserva::SERVICIO_CONSUMIDO : Reserva::SERVICIO_CONTRATADO,
                'created_at' => $creada,
                'updated_at' => $creada,
            ]);
        }

        $this->recorrerEstados($reserva, $estado, $creada, $habitacion, $cuenta, $recepcion);

        return $reserva;
    }

    /**
     * Escribe el camino que siguio la reserva hasta su estado final:
     * historial, pagos, comprobantes y correos, cada uno con su fecha.
     */
    private function recorrerEstados(Reserva $reserva, int $estadoFinal, Carbon $creada, Habitacion $habitacion, ?User $cuenta, User $recepcion): void
    {
        $web = $reserva->canal === Reserva::CANAL_WEB;
        $llegada = $reserva->fecha_llegada->copy();
        $salida = $reserva->fecha_salida->copy();
        $momento = $creada->copy();

        $this->historial($reserva, null, Reserva::PENDIENTE, null, $web ? $cuenta : $recepcion, $momento);
        if ($web) {
            $this->notificar($reserva, Notificacion::REGISTRO, "Recibimos tu reserva {$reserva->codigo}", $cuenta, $momento);
        }

        if ($estadoFinal === Reserva::PENDIENTE) {
            //Algunas web se quedan sin pagar: un intento rechazado deja constancia
            if ($web && mt_rand(1, 100) <= 35) {
                $this->pagoPasarela($reserva, $cuenta, $momento->copy()->addMinutes(4), false);
            }
            $this->finalizar($reserva, $momento);
            return;
        }

        //Cancelada sin confirmar: el huesped no pago o desistio
        $cancelarAntes = $estadoFinal === Reserva::CANCELADA && mt_rand(1, 100) <= 55;

        if (!$cancelarAntes) {
            $momento = $this->confirmar($reserva, $web, $cuenta, $recepcion, $momento);
        }

        if ($estadoFinal === Reserva::CANCELADA) {
            $this->cancelar($reserva, $web, $cuenta, $recepcion, $momento, $llegada, !$cancelarAntes);
            return;
        }

        if ($estadoFinal === Reserva::CONFIRMADA) {
            $this->finalizar($reserva, $momento);
            return;
        }

        //Entrada: la tarde del dia de llegada (sin pasar de ahora)
        $entrada = $llegada->copy()->setTime(mt_rand(13, 18), mt_rand(0, 59));
        if ($entrada->gt($this->ahora)) {
            $entrada = $this->ahora->copy()->subMinutes(mt_rand(20, 120));
        }
        //Nunca antes de la confirmacion (los que llegan sin reserva confirman y entran casi a la vez)
        if ($entrada->lte($momento)) {
            $entrada = $momento->copy()->addMinutes(mt_rand(5, 15))->min($this->ahora);
        }
        $reserva->check_in_at = $entrada;
        $this->historial($reserva, Reserva::CONFIRMADA, Reserva::EN_ESTADIA, "Entrada registrada en la habitación {$habitacion->numero}.", $recepcion, $entrada);

        $comprobante = $reserva->comprobantes()->where('estado', Comprobante::VIGENTE)->first();
        if ($comprobante) {
            $comprobante->update(['verificado_at' => $entrada->copy()->subMinutes(2), 'verificado_por' => $recepcion->id]);
        }

        if ($estadoFinal === Reserva::EN_ESTADIA) {
            $this->finalizar($reserva, $entrada);
            return;
        }

        //Salida: la manana del dia de salida con el saldo cobrado
        $salidaHora = $salida->copy()->setTime(mt_rand(8, 11), mt_rand(0, 59));
        if ($salidaHora->gt($this->ahora)) {
            $salidaHora = $this->ahora->copy()->subMinutes(mt_rand(5, 60));
        }
        $reserva->check_out_at = $salidaHora;

        $saldo = round((float) $reserva->total - (float) $reserva->pagos()->where('estado_pago', Pago::APROBADO)->sum('monto'), 2);
        $cobro = '';
        if ($saldo > 0) {
            $metodo = $this->elegir(['tarjeta' => 55, 'efectivo' => 35, 'transferencia' => 10]);
            $this->pagoRecepcion($reserva, Pago::SALDO, $saldo, $metodo, $recepcion, $salidaHora);
            $cobro = ' Saldo cobrado: $' . number_format($saldo, 2) . ' (' . \App\Services\RecepcionService::METODOS[$metodo] . ').';
        }
        $this->historial($reserva, Reserva::EN_ESTADIA, Reserva::FINALIZADA, 'Salida registrada.' . $cobro, $recepcion, $salidaHora);

        if ($salida->isSameDay($this->hoy)) {
            $this->salenHoy[] = $habitacion->id;
        }

        $this->finalizar($reserva, $salidaHora);
    }

    private function confirmar(Reserva $reserva, bool $web, ?User $cuenta, User $recepcion, Carbon $momento): Carbon
    {
        $anticipo = (float) $reserva->anticipo_requerido;

        if ($web) {
            //A veces la primera tarjeta es rechazada y el huesped paga con otra
            if (mt_rand(1, 100) <= 12) {
                $this->pagoPasarela($reserva, $cuenta, $momento->copy()->addMinutes(3), false);
            }
            $momento = $momento->copy()->addMinutes(mt_rand(5, 25));
            $pago = $this->pagoPasarela($reserva, $cuenta, $momento, true);
            $comentario = 'Anticipo de $' . number_format($anticipo, 2) . ' aprobado (autorización '
                . $pago->respuesta_pasarela['autorizacion'] . ').';
            $this->historial($reserva, Reserva::PENDIENTE, Reserva::CONFIRMADA, $comentario, null, $momento);
        } else {
            //Por telefono el anticipo llega horas despues por transferencia; en recepcion se paga al momento
            $presencial = $reserva->canal === Reserva::CANAL_PRESENCIAL;
            $momento = $momento->copy()->addMinutes($presencial ? mt_rand(3, 10) : mt_rand(60, 1200));
            if ($momento->gt($this->ahora)) {
                $momento = $this->ahora->copy()->subMinutes(mt_rand(1, 30));
            }
            $metodo = $presencial ? $this->elegir(['efectivo' => 50, 'tarjeta' => 50]) : 'transferencia';
            $monto = $presencial && mt_rand(0, 1) ? (float) $reserva->total : $anticipo;

            $this->pagoRecepcion($reserva, Pago::ANTICIPO, $monto, $metodo, $recepcion, $momento);
            $comentario = 'Confirmada en recepción con anticipo de $' . number_format($monto, 2)
                . ' (' . \App\Services\RecepcionService::METODOS[$metodo] . ').';
            $this->historial($reserva, Reserva::PENDIENTE, Reserva::CONFIRMADA, $comentario, $recepcion, $momento);
        }

        $reserva->comprobantes()->create([
            'numero' => 'CMP-' . substr($reserva->codigo, 3) . '-1',
            'version' => 1,
            'qr_token' => (string) Str::uuid(),
            'emitido_at' => $momento,
            'estado' => Comprobante::VIGENTE,
            'created_at' => $momento,
            'updated_at' => $momento,
        ]);

        if ($reserva->huesped->email) {
            $this->notificar($reserva, Notificacion::CONFIRMACION, "Tu reserva {$reserva->codigo} está confirmada", $cuenta, $momento->copy()->addSeconds(30));
        }

        return $momento;
    }

    private function cancelar(Reserva $reserva, bool $web, ?User $cuenta, User $recepcion, Carbon $momento, Carbon $llegada, bool $confirmada): void
    {
        $limite = $llegada->copy()->setTime(14, 0)->min($this->ahora);
        $cuando = $momento->copy()->addHours(mt_rand(2, 72));
        if ($cuando->gte($limite)) {
            $cuando = $momento->copy()->addMinutes((int) max(5, $momento->diffInMinutes($limite) / 2));
        }

        $motivos = ['Cambio de planes de viaje.', 'Vuelo cancelado.', 'Motivos de salud.', 'Encontró otra opción.',
            'Reservó las fechas equivocadas.', 'Motivos de trabajo.', null];
        $motivo = $motivos[mt_rand(0, count($motivos) - 1)];

        $porHuesped = $web && mt_rand(0, 1);
        $pagado = round((float) $reserva->pagos()->where('estado_pago', Pago::APROBADO)->sum('monto'), 2);

        $comentario = ($porHuesped ? 'Cancelada por el huésped. ' : 'Cancelada en recepción. ')
            . ($pagado > 0 ? 'Reembolso: 100 % del anticipo pagado ($' . number_format($pagado, 2) . ').' : 'Sin anticipo pagado, no hay reembolso.')
            . ($motivo ? " Motivo: $motivo" : '');

        $reserva->fill(['cancelada_at' => $cuando, 'motivo_cancelacion' => $motivo]);
        $this->historial($reserva, $confirmada ? Reserva::CONFIRMADA : Reserva::PENDIENTE, Reserva::CANCELADA,
            $comentario, $porHuesped ? $cuenta : $recepcion, $cuando);

        $reserva->comprobantes()->where('estado', Comprobante::VIGENTE)->update(['estado' => Comprobante::ANULADO]);

        if ($reserva->huesped->email) {
            $this->notificar($reserva, Notificacion::CANCELACION, "Tu reserva {$reserva->codigo} fue cancelada", $cuenta, $cuando->copy()->addSeconds(40));
        }

        $this->finalizar($reserva, $cuando);
    }

    private function finalizar(Reserva $reserva, Carbon $momento): void
    {
        $reserva->updated_at = $momento;
        $reserva->save();
    }

    private function historial(Reserva $reserva, ?int $anterior, int $nuevo, ?string $comentario, ?User $user, Carbon $momento): void
    {
        $fila = new ReservaHistorial([
            'reserva_id' => $reserva->id,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'comentario' => $comentario,
            'user_id' => $user?->id,
        ]);
        $fila->created_at = $momento;
        $fila->save();
    }

    private function pagoPasarela(Reserva $reserva, User $cuenta, Carbon $momento, bool $aprobado): Pago
    {
        $tarjetas = [['4242424242424242', 'Visa'], ['5555555555554444', 'Mastercard'], ['378282246310005', 'American Express']];
        [$numero, $marca] = $aprobado ? $tarjetas[mt_rand(0, 2)] : ['4000000000009995', 'Visa'];
        $referencia = (string) Str::uuid();

        $pago = new Pago([
            'reserva_id' => $reserva->id,
            'tipo_pago' => Pago::ANTICIPO,
            'metodo_pago' => 'tarjeta',
            'pasarela' => 'sandbox',
            'referencia_externa' => $referencia,
            'monto' => $reserva->anticipo_requerido,
            'estado_pago' => $aprobado ? Pago::APROBADO : Pago::RECHAZADO,
            'respuesta_pasarela' => [
                'referencia' => $referencia,
                'estado' => $aprobado ? 'aprobado' : 'rechazado',
                'motivo' => $aprobado ? null : 'Fondos insuficientes.',
                'autorizacion' => $aprobado ? strtoupper(Str::random(6)) : null,
                'monto' => number_format((float) $reserva->anticipo_requerido, 2, '.', ''),
                'moneda' => 'USD',
                'marca' => $marca,
                'ultimos4' => substr($numero, -4),
                'titular' => mb_strtoupper($cuenta->name),
                'fecha' => $momento->toIso8601String(),
                'ambiente' => 'pruebas',
            ],
            'pagado_at' => $aprobado ? $momento : null,
            'user_id' => $cuenta->id,
        ]);
        $pago->created_at = $momento->copy()->subMinutes(2);
        $pago->updated_at = $momento;
        $pago->save();

        return $pago;
    }

    private function pagoRecepcion(Reserva $reserva, string $tipo, float $monto, string $metodo, User $recepcion, Carbon $momento): Pago
    {
        $referencia = match ($metodo) {
            'transferencia' => 'TRF-' . $momento->format('ymd') . '-' . strtoupper(Str::random(5)),
            'tarjeta' => 'POS-' . mt_rand(100000, 999999) . '-' . strtoupper(Str::random(3)),
            default => null,
        };

        $pago = new Pago([
            'reserva_id' => $reserva->id,
            'tipo_pago' => $tipo,
            'metodo_pago' => $metodo,
            'referencia_externa' => $referencia,
            'monto' => round($monto, 2),
            'estado_pago' => Pago::APROBADO,
            'pagado_at' => $momento,
            'user_id' => $recepcion->id,
        ]);
        $pago->created_at = $momento;
        $pago->updated_at = $momento;
        $pago->save();

        return $pago;
    }

    private function notificar(Reserva $reserva, string $tipo, string $asunto, ?User $cuenta, Carbon $momento): void
    {
        $destino = $reserva->huesped->email;
        if (!$destino || $momento->gt($this->ahora)) {
            return;
        }

        //Un correo de cada veinte rebota la primera vez
        $falla = mt_rand(1, 20) === 1;

        $fila = new Notificacion([
            'reserva_id' => $reserva->id,
            'user_id' => $cuenta?->id,
            'tipo' => $tipo,
            'canal' => Notificacion::CANAL_CORREO,
            'destinatario' => $destino,
            'asunto' => $asunto,
            'estado_envio' => $falla ? Notificacion::FALLIDO : Notificacion::ENVIADO,
            'intentos' => $falla ? 3 : 1,
            'error' => $falla ? '550 5.1.1 The email account that you tried to reach does not exist.' : null,
            'enviado_at' => $falla ? null : $momento,
        ]);
        $fila->created_at = $momento;
        $fila->updated_at = $momento;
        $fila->save();
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    //Primera habitacion del tipo libre todas las noches (las de mantenimiento no se ofrecen a futuro)
    private function habitacionLibre(int $tipoId, Carbon $llegada, int $noches, bool $futura): ?Habitacion
    {
        $candidatas = $this->habitaciones->where('tipo_habitacion_id', $tipoId)
            ->when($futura, fn ($c) => $c->where('estado_habitacion', '!=', Habitacion::MANTENIMIENTO))
            ->shuffle(mt_rand());

        foreach ($candidatas as $habitacion) {
            $libre = true;
            for ($i = 0; $i < $noches; $i++) {
                if (isset($this->ocupacion[$habitacion->id][$llegada->copy()->addDays($i)->toDateString()])) {
                    $libre = false;
                    break;
                }
            }
            if ($libre) {
                return $habitacion;
            }
        }

        return null;
    }

    private function momentoCreacion(Carbon $llegada, string $canal, int $estado): Carbon
    {
        if ($estado === Reserva::PENDIENTE) {
            //La mayoria sigue vigente (menos de 24 h); unas pocas ya vencieron sin pagarse
            return mt_rand(1, 100) <= 75
                ? $this->ahora->copy()->subMinutes(mt_rand(15, 20 * 60))
                : $this->ahora->copy()->subDays(mt_rand(2, 6))->setTime(mt_rand(8, 22), mt_rand(0, 59));
        }

        if ($canal === Reserva::CANAL_PRESENCIAL) {
            $momento = $llegada->copy()->setTime(mt_rand(10, 13), mt_rand(0, 59));
        } else {
            $momento = $llegada->copy()->subDays($this->elegir([1 => 10, 3 => 15, 7 => 25, 14 => 20, 30 => 18, 60 => 12]) + mt_rand(0, 3))
                ->setTime(mt_rand(7, 22), mt_rand(0, 59));
        }

        return $momento->gt($this->ahora) ? $this->ahora->copy()->subHours(mt_rand(1, 6)) : $momento;
    }

    //[adultos, ninos, tipo] coherentes con la capacidad de cada tipo
    private function elegirGrupo(): array
    {
        return match ($this->elegir(['solo' => 18, 'pareja' => 42, 'familia' => 28, 'lujo' => 12])) {
            'solo' => [1, 0, 'Sencilla'],
            'pareja' => [2, 0, 'Doble'],
            'familia' => mt_rand(0, 1) ? [2, mt_rand(1, 2), 'Familiar'] : [mt_rand(3, 4), 0, 'Familiar'],
            'lujo' => [2, 0, 'Suite'],
        };
    }

    private function serviciosPara(string $tipo): array
    {
        $comunes = ['Alimentación', 'Lavandería', 'Transporte', 'Actividades recreativas', 'Tour Ruta de las Flores',
            'Estacionamiento vigilado', 'Salida tardía', 'Masaje relajante'];
        if (in_array($tipo, ['Doble', 'Suite'])) {
            $comunes[] = 'Cena romántica en la playa';
        }

        return array_values(array_filter($comunes, fn ($n) => $this->servicios->has($n)));
    }

    private function notaReserva(): string
    {
        $notas = ['Celebran luna de miel.', 'Solicita cama extra para un niño.', 'Llega en vuelo nocturno.',
            'Pide habitación lejos del ascensor.', 'Cumpleaños del huésped durante la estadía.', 'Requiere cuna.',
            'Factura a nombre de la empresa.', 'Viene por un congreso en San Salvador.', 'Pide almohadas extra.'];

        return $notas[mt_rand(0, count($notas) - 1)];
    }

    private function huespedConCuenta(): Huesped
    {
        return $this->huespedesCuenta[mt_rand(0, count($this->huespedesCuenta) - 1)];
    }

    private function huespedSinCuenta(): Huesped
    {
        return $this->huespedesSinCuenta[mt_rand(0, count($this->huespedesSinCuenta) - 1)];
    }

    //Elige una clave segun su peso: ['a' => 70, 'b' => 30]
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

    //Las habitaciones que se desocuparon esta manana quedan en limpieza
    private function actualizarEstadoHabitaciones(): void
    {
        Habitacion::whereIn('id', array_unique($this->salenHoy))
            ->where('estado_habitacion', Habitacion::DISPONIBLE)
            ->update(['estado_habitacion' => Habitacion::LIMPIEZA]);
    }
}
