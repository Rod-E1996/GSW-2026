<?php

namespace Database\Seeders;

use App\Models\AuditarDetalle;
use App\Models\AuditarTabla;
use App\Models\BloqueoHabitacion;
use App\Models\Configuracion;
use App\Models\ErrorLog;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\QueueControl;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\SessionLog;
use App\Models\Tarifa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Actividad de demostracion de los modulos de administracion, coherente con los
 * datos de DemoHotelSeeder (corre despues de el):
 *
 * - Auditoria: lo que hizo el personal (alta de usuarios y huespedes, bloqueos,
 *   tarifas, cambios de estado de habitaciones, parametros, perfil...).
 * - Error log: errores resueltos y por resolver.
 * - Procesos en segundo plano: los envios de correo que generaron esos errores
 *   y los avisos de nuevo dispositivo.
 * - Historial de accesos (sessions_logs) de los huespedes con cuenta.
 *
 * Todo se escribe con eventos apagados y sin despachar trabajos: no se envia
 * ningun correo. Solo corre si la tabla error_logs esta vacia.
 */
class ActividadSistemaSeeder extends Seeder
{
    const AGREGAR = 1;
    const EDITAR = 2;
    const ELIMINAR = 3;
    const ACTIVAR = 4;
    const DESACTIVAR = 5;

    private Carbon $ahora;
    private ?User $admin = null;
    private ?User $gerencia = null;
    private array $staff = [];
    private array $tablas = [];

    public function run()
    {
        if (ErrorLog::count() > 0) {
            $this->command?->warn('Ya hay registros en el error log: se omite ActividadSistemaSeeder.');
            return;
        }

        mt_srand(2028);
        $this->ahora = Carbon::now();

        $this->admin = User::where('email', 'admin@gmail.com')->first() ?? User::role('Super Administrador')->first();
        $this->gerencia = User::where('email', 'gerencia@hotellink.com')->first() ?? $this->admin;
        $this->staff = User::role('Recepcionista')->where('estado', 1)->orderBy('id')->get()->all();
        if (!$this->admin || !$this->staff) {
            $this->command?->warn('Faltan usuarios del personal: se omite ActividadSistemaSeeder.');
            return;
        }

        Model::withoutEvents(function () {
            DB::transaction(function () {
                $this->auditoria();
                $this->erroresYProcesos();
                $this->accesos();
            });
        });

        $this->command?->info('Actividad: ' . AuditarDetalle::count() . ' registros de auditoría, '
            . ErrorLog::count() . ' errores, ' . QueueControl::count() . ' procesos y '
            . SessionLog::count() . ' accesos.');
    }

    // ------------------------------------------------------------------
    // Auditoria
    // ------------------------------------------------------------------

    private function auditoria(): void
    {
        $this->cargaInicial();

        //Alta del personal por la gerencia y baja del exempleado
        foreach (User::role('Recepcionista')->orderBy('id')->get() as $user) {
            if ($user->email === 'recepcion@hotellink.com') {
                continue;
            }
            $this->auditar(self::AGREGAR, $user, null, $user, $this->gerencia, $user->created_at);

            if ((int) $user->estado === 0) {
                $momento = $this->ahora->copy()->subDays(mt_rand(20, 50))->setTime(17, mt_rand(0, 59))
                    ->max($user->created_at->copy()->addDays(5));
                $antes = (clone $user)->forceFill(['estado' => 1]);
                $this->auditar(self::DESACTIVAR, $user, $antes, $user, $this->gerencia, $momento);
            }
        }

        //Huespedes sin cuenta registrados en recepcion cuando hicieron su primera reserva
        $huespedes = Huesped::whereNull('user_id')->with(['reservas' => fn ($q) => $q->orderBy('created_at')])->get();
        foreach ($huespedes as $huesped) {
            $primera = $huesped->reservas->first();
            if ($primera) {
                $this->auditar(self::AGREGAR, $huesped, null, $huesped, $this->staffAzar(), $primera->created_at->copy()->subMinutes(mt_rand(2, 8)));
            }
        }

        //Bloqueos creados por la gerencia
        foreach (BloqueoHabitacion::with('habitacion')->get() as $bloqueo) {
            $this->auditar(self::AGREGAR, $bloqueo, null, $bloqueo, $this->gerencia, $bloqueo->created_at, 'Habitación ' . $bloqueo->habitacion?->numero);
        }

        //Tarifas especiales
        foreach (Tarifa::with(['tipoHabitacion', 'temporada'])->get() as $tarifa) {
            $nombre = trim(($tarifa->tipoHabitacion->nombre ?? '') . ' · ' . ($tarifa->temporada->nombre ?? ''), ' ·');
            $this->auditar(self::AGREGAR, $tarifa, null, $tarifa, $this->gerencia,
                $this->ahora->copy()->subDays(mt_rand(60, 120))->setTime(10, mt_rand(0, 59)), $nombre);
        }

        //Habitaciones: limpieza tras cada salida del ultimo mes y vuelta a disponible
        $salidas = Reserva::with('habitacion')
            ->where('estado_reserva', Reserva::FINALIZADA)
            ->whereNotNull('habitacion_id')
            ->where('check_out_at', '>=', $this->ahora->copy()->subDays(30))
            ->orderBy('check_out_at')
            ->get();
        foreach ($salidas as $reserva) {
            $habitacion = $reserva->habitacion;
            if (!$habitacion) {
                continue;
            }
            $salida = $reserva->check_out_at->copy();
            $limpieza = (clone $habitacion)->forceFill(['estado_habitacion' => Habitacion::LIMPIEZA, 'updated_at' => $salida]);
            $disponible = (clone $habitacion)->forceFill(['estado_habitacion' => Habitacion::DISPONIBLE]);

            $this->auditar(self::EDITAR, $habitacion, $disponible, $limpieza, $this->staffAzar(), $salida->copy()->addMinutes(mt_rand(1, 10)));

            //Si salio hoy, la habitacion sigue en limpieza (DemoHotelSeeder la deja asi)
            $lista = $salida->copy()->addMinutes(mt_rand(60, 150));
            if (!$salida->isToday() && $lista->lt($this->ahora)) {
                $this->auditar(self::EDITAR, $habitacion, $limpieza, $disponible->forceFill(['updated_at' => $lista]), $this->staffAzar(), $lista);
            }
        }

        //Habitacion enviada a mantenimiento por el bloqueo vigente
        $bloqueoActual = BloqueoHabitacion::with('habitacion')
            ->where('fecha_inicio', '<=', $this->ahora->toDateString())
            ->where('fecha_fin', '>=', $this->ahora->toDateString())
            ->first();
        if ($bloqueoActual?->habitacion) {
            $h = $bloqueoActual->habitacion;
            $this->auditar(self::EDITAR, $h,
                (clone $h)->forceFill(['estado_habitacion' => Habitacion::DISPONIBLE]),
                (clone $h)->forceFill(['estado_habitacion' => Habitacion::MANTENIMIENTO]),
                $this->gerencia, Carbon::parse($bloqueoActual->fecha_inicio)->setTime(8, 15));
        }

        //Ajustes de precios de servicios
        foreach (Servicio::orderBy('id')->take(3)->get() as $servicio) {
            $antes = (clone $servicio)->forceFill(['precio' => max(1, (float) $servicio->precio - 5)]);
            $this->auditar(self::EDITAR, $servicio, $antes, $servicio, $this->gerencia,
                $this->ahora->copy()->subDays(mt_rand(15, 75))->setTime(11, mt_rand(0, 59)));
        }

        //Parametro del sistema revisado por la gerencia (el valor actual queda como el nuevo)
        $parametro = Configuracion::where('clave', 'reserva_pendiente_horas')->first();
        if ($parametro) {
            $antes = (clone $parametro)->forceFill(['valor' => '12']);
            $this->auditar(self::EDITAR, $parametro, $antes, $parametro, $this->gerencia,
                $this->ahora->copy()->subDays(12)->setTime(9, 40), $parametro->clave);
        }

        //Huespedes que actualizaron su telefono desde el perfil
        $perfil = AuditarTabla::firstOrCreate(['tabla' => 'perfil', 'modelo' => User::class]);
        $cuentas = User::role('Huésped')->where('estado', 1)->whereNotNull('email_verified_at')->inRandomOrder(mt_rand())->take(4)->get();
        foreach ($cuentas as $cuenta) {
            $antes = (clone $cuenta)->forceFill(['phone' => null]);
            $this->registrar(self::EDITAR, $perfil->id, $cuenta, $antes, $cuenta, $cuenta,
                $this->ahora->copy()->subDays(mt_rand(3, 90))->setTime(mt_rand(8, 22), mt_rand(0, 59)));
        }
    }

    /**
     * Los catalogos se siembran con eventos encendidos y dejan filas de auditoria
     * sin usuario y con la fecha de hoy, que taparian el resto en el listado.
     * Se presentan como la instalacion del sistema: hechas por el Super
     * Administrador antes de toda la historia de la demo (y de las cuentas).
     */
    private function cargaInicial(): void
    {
        $instalacion = $this->ahora->copy()->subDays(410)->setTime(9, 0);
        $habitaciones = Habitacion::pluck('numero', 'id');
        $tablaHabitaciones = AuditarTabla::where('modelo', Habitacion::class)->value('id');

        foreach (AuditarDetalle::whereNull('user_id')->orderBy('id')->get() as $i => $fila) {
            $momento = $instalacion->copy()->addSeconds($i * 20);
            $fila->user_id = $this->admin->id;
            $fila->name = $this->admin->name;
            $fila->email = $this->admin->email;
            if (!$fila->nombre_modificado && (int) $fila->auditar_tabla_id === (int) $tablaHabitaciones) {
                $fila->nombre_modificado = 'Habitación ' . ($habitaciones[$fila->primary_key] ?? '');
            }
            $fila->created_at = $momento;
            $fila->updated_at = $momento;
            $fila->save();
        }

        //La cuenta del Super Administrador tambien nace con la instalacion
        if ($this->admin->created_at->gt($instalacion)) {
            $this->admin->forceFill(['created_at' => $instalacion, 'email_verified_at' => $instalacion])->save();
        }
    }

    private function auditar(int $accion, Model $modelo, ?Model $antes, Model $despues, User $autor, Carbon $momento, ?string $nombre = null): void
    {
        $this->registrar($accion, $this->tabla($modelo), $modelo, $antes, $despues, $autor, $momento, $nombre);
    }

    private function registrar(int $accion, int $tablaId, Model $modelo, ?Model $antes, Model $despues, User $autor, Carbon $momento, ?string $nombre = null): void
    {
        if ($momento->gt($this->ahora)) {
            $momento = $this->ahora->copy()->subMinutes(mt_rand(5, 60));
        }

        $fila = new AuditarDetalle([
            'user_id' => $autor->id,
            'auditar_accion_id' => $accion,
            'auditar_tabla_id' => $tablaId,
            'primary_key' => $modelo->getKey(),
            'antes' => $antes?->toJson(JSON_UNESCAPED_UNICODE),
            'despues' => $despues->toJson(JSON_UNESCAPED_UNICODE),
        ]);
        $fila->name = $autor->name;
        $fila->email = $autor->email;
        $fila->nombre_modificado = $nombre ?? ($modelo instanceof Habitacion ? 'Habitación ' . $modelo->numero
            : ($modelo->name ?? $modelo->nombre ?? $modelo->nombres ?? ''));
        $fila->created_at = $momento;
        $fila->updated_at = $momento;
        $fila->save();
    }

    //Mismo criterio que los listeners de auditoria: una fila por modelo
    private function tabla(Model $modelo): int
    {
        $clase = get_class($modelo);

        return $this->tablas[$clase] ??= AuditarTabla::where('modelo', $clase)->where('tabla', '!=', 'perfil')->value('id')
            ?? AuditarTabla::create(['tabla' => $modelo->getTable(), 'modelo' => $clase])->id;
    }

    // ------------------------------------------------------------------
    // Error log y procesos en segundo plano
    // ------------------------------------------------------------------

    private function erroresYProcesos(): void
    {
        $admins = Role::where('name', 'Super Administrador')->first()?->users->where('estado', 1)->count() ?: 1;
        $recepcion = $this->staff[0];

        //Codigos de reservas reales para que los parametros apunten a algo que existe
        $codigos = Reserva::inRandomOrder(mt_rand())->take(4)->pluck('codigo')->all() + array_fill(0, 4, 'HL-000000');
        $habitacion = Habitacion::inRandomOrder(mt_rand())->value('id') ?? 1;
        $comprobante = \App\Models\Comprobante::inRandomOrder(mt_rand())->value('numero') ?? 'CMP-000000-1';

        //[dias atras, controller, mensaje, parametros, usuario, resuelto]
        $errores = [
            [84, 'App\Http\Controllers\Api\Admin\ReservaController@checkout',
                'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
                ['codigo' => $codigos[0]], $recepcion, true],
            [71, 'App\Http\Controllers\Api\ReservaController@store',
                'Connection could not be established with host "smtp.hotellink.com:587": stream_socket_client(): unable to connect (Connection timed out)',
                [], null, true],
            [58, 'App\Http\Controllers\Api\Admin\ReporteController@pdf',
                'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)',
                [], $this->gerencia, true],
            [43, 'App\Http\Controllers\Api\Admin\HabitacionController@update',
                'Undefined array key "tipo_habitacion_id"',
                ['id' => (string) $habitacion], $this->gerencia, true],
            [30, 'App\Http\Controllers\Api\PagoController@notificacion',
                'cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received',
                [], null, true],
            [16, 'App\Http\Controllers\Api\Admin\ComprobanteController@verificar',
                'No query results for model [App\Models\Comprobante].',
                ['valor' => $comprobante . 'X'], $this->staff[1] ?? $recepcion, true],
            [6, 'App\Http\Controllers\Api\ReservaController@store',
                'Connection could not be established with host "smtp.hotellink.com:587": stream_socket_client(): unable to connect (Connection refused)',
                [], null, false],
            [3, 'App\Http\Controllers\Api\Admin\RespaldoController@store',
                'fopen(/var/www/storage/app/respaldos/hotellink-' . $this->ahora->copy()->subDays(3)->format('Ymd') . '-020000.sql): Failed to open stream: No space left on device',
                [], $this->admin, false],
            [1, 'App\Http\Controllers\Api\Admin\ReservaController@update',
                'Call to a member function format() on null',
                ['codigo' => $codigos[1]], $this->staff[count($this->staff) - 1], false],
        ];

        foreach ($errores as [$dias, $controller, $mensaje, $parametros, $user, $resuelto]) {
            $momento = $this->ahora->copy()->subDays($dias)->setTime(mt_rand(7, 21), mt_rand(0, 59), mt_rand(0, 59));
            if ($momento->gt($this->ahora)) {
                $momento = $this->ahora->copy()->subMinutes(mt_rand(30, 180));
            }

            $error = new ErrorLog([
                'controller' => $controller,
                'mensaje' => $mensaje,
                'parametros' => json_encode((object) $parametros),
                'user_id' => $user?->id,
                'estado' => $resuelto ? 1 : 0,
            ]);
            $error->created_at = $momento;
            $error->updated_at = $resuelto ? $momento->copy()->addHours(mt_rand(2, 30))->min($this->ahora) : $momento;
            $error->save();

            //Cada error dispara el envio por correo a los administradores
            $inicio = $momento->copy()->addSeconds(mt_rand(1, 4));
            $this->proceso('Envio de error log a los administradores por email', $admins, $user, $inicio, $inicio->copy()->addSeconds(mt_rand(2, 9)));
        }
    }

    private function proceso(string $titulo, int $total, ?User $user, Carbon $inicio, Carbon $fin): void
    {
        $proceso = new QueueControl([
            'titulo' => $titulo,
            'total_procesos' => $total,
            'progreso' => $total,
            'pendiente' => 0,
            'fecha_inicio' => $inicio->toDateTimeString(),
            'fecha_fin' => $fin->toDateTimeString(),
            'user_id' => $user?->id,
            'estado' => 1,
        ]);
        $proceso->created_at = $inicio;
        $proceso->updated_at = $fin;
        $proceso->save();
    }

    // ------------------------------------------------------------------
    // Historial de accesos
    // ------------------------------------------------------------------

    /**
     * Un inicio de sesion antes de cada reserva hecha en el portal. Solo para las
     * cuentas de huespedes de la demo (@example.com): asi el aviso de "nuevo
     * dispositivo" nunca le escribe a un buzon real por culpa de este historial.
     * Todas quedan cerradas (estado 0) y sin token: la demo no crea sesiones vivas.
     */
    private function accesos(): void
    {
        $dispositivos = [
            ['device' => 'WebKit', 'platform' => 'Windows 10.0', 'browser' => 'Chrome 130.0.0.0', 'device_type' => 'Desktop', 'device_model' => 'Windows 10.0'],
            ['device' => 'WebKit', 'platform' => 'Windows 10.0', 'browser' => 'Edge 130.0.0.0', 'device_type' => 'Desktop', 'device_model' => 'Windows 10.0'],
            ['device' => 'Macintosh', 'platform' => 'OS X 10_15_7', 'browser' => 'Safari 17.6', 'device_type' => 'Desktop', 'device_model' => 'Mac OS X 10.15.7'],
            ['device' => 'iPhone', 'platform' => 'iOS 17_6', 'browser' => 'Safari 17.6', 'device_type' => 'Mobile', 'device_model' => 'iPhone'],
            ['device' => 'Samsung', 'platform' => 'AndroidOS 14', 'browser' => 'Chrome 130.0.6723.102', 'device_type' => 'Mobile', 'device_model' => 'Android'],
            ['device' => 'iPad', 'platform' => 'iOS 17_6', 'browser' => 'Safari 17.6', 'device_type' => 'Tablet', 'device_model' => 'iPad'],
        ];

        $reservas = Reserva::with('huesped.user')
            ->where('canal', Reserva::CANAL_WEB)
            ->whereHas('huesped.user', fn ($q) => $q->where('email', 'like', '%@example.com'))
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (Reserva $r) => $r->huesped->user_id);

        foreach ($reservas as $userId => $deUsuario) {
            $user = $deUsuario->first()->huesped->user;

            //Cada huesped usa uno o dos dispositivos, con su propia IP de casa
            $propios = (array) array_rand($dispositivos, mt_rand(1, 2));
            $ip = '198.51.100.' . (10 + $userId % 240);
            $vistos = [];

            foreach ($deUsuario as $reserva) {
                $d = $dispositivos[$propios[mt_rand(0, count($propios) - 1)]];
                $momento = $reserva->created_at->copy()->subMinutes(mt_rand(2, 25));

                //Un dispositivo nuevo (que no sea el primer acceso) genera el aviso por correo
                if ($vistos && !in_array($d['device_model'], $vistos) && (int) $user->login_notificacion === 1) {
                    $this->proceso('Notificación de nuevo dispositivo conectado', 1, $user, $momento->copy()->addSeconds(3), $momento->copy()->addSeconds(mt_rand(5, 12)));
                }
                $vistos[] = $d['device_model'];

                //Sin session_id: el id de un token inventado coincidiria con los tokens reales futuros
                $log = new SessionLog($d + [
                    'session_id' => null,
                    'user_id' => $user->id,
                    'ip_address' => $d['device_type'] === 'Mobile' && mt_rand(0, 1) ? '203.0.113.' . mt_rand(2, 250) : $ip,
                    'estado' => 0,
                ]);
                $log->created_at = $momento;
                $log->updated_at = $momento->copy()->addMinutes(mt_rand(15, 90))->min($this->ahora);
                $log->save();
            }
        }
    }

    private function staffAzar(): User
    {
        return $this->staff[mt_rand(0, count($this->staff) - 1)];
    }
}
