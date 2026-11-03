<?php

namespace Tests\Feature\Api;

use App\Jobs\SendEmail;
use App\Jobs\SendEmailNuevoDispositivo;
use App\Mail\ErrorLogMail;
use App\Mail\NuevoDispositivoEmail;
use App\Models\ErrorLog;
use App\Models\QueueControl;
use App\Models\SessionLog;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CorreosColaTest extends ApiTestCase
{
    private function dispositivoAnterior(int $userId): void
    {
        SessionLog::create([
            'session_id' => 'anterior',
            'user_id' => $userId,
            'ip_address' => '10.0.0.1',
            'device_type' => 'Desktop',
            'device_model' => 'Otro equipo',
            'estado' => 0,
        ]);
    }

    private function errorDePrueba(): ErrorLog
    {
        return ErrorLog::withoutEvents(fn () => ErrorLog::create([
            'controller' => 'ControladorDePrueba@accion',
            'mensaje' => 'Fallo de prueba',
            'parametros' => '[]',
        ]));
    }

    public function test_login_desde_dispositivo_nuevo_encola_el_aviso()
    {
        Bus::fake();
        $huesped = $this->huesped();
        $huesped->update(['login_notificacion' => 1]);
        $this->dispositivoAnterior($huesped->id);

        $this->postJson('/api/login', ['email' => $huesped->email, 'password' => '12345678'])->assertOk();

        Bus::assertDispatched(SendEmailNuevoDispositivo::class);
    }

    public function test_el_primer_login_y_el_aviso_desactivado_no_encolan_nada()
    {
        Bus::fake();
        $huesped = $this->huesped();
        $huesped->update(['login_notificacion' => 1]);

        $this->postJson('/api/login', ['email' => $huesped->email, 'password' => '12345678'])->assertOk();
        Bus::assertNotDispatched(SendEmailNuevoDispositivo::class);

        $recepcion = $this->recepcion();
        $recepcion->update(['login_notificacion' => 0]);
        $this->dispositivoAnterior($recepcion->id);

        $this->postJson('/api/login', ['email' => $recepcion->email, 'password' => '12345678'])->assertOk();
        Bus::assertNotDispatched(SendEmailNuevoDispositivo::class);
    }

    public function test_los_correos_pasan_por_la_tabla_jobs()
    {
        config(['queue.default' => 'database']);
        $antes = DB::table('jobs')->count();

        SendEmail::dispatch(collect(['admin@gmail.com']), $this->errorDePrueba());
        SendEmailNuevoDispositivo::dispatch($this->huesped(), '10.0.0.2', 'Windows', 'Chrome', 'Desktop');

        $this->assertSame($antes + 2, DB::table('jobs')->count());
    }

    public function test_el_trabajo_de_nuevo_dispositivo_envia_el_correo_y_registra_el_avance()
    {
        Mail::fake();
        $huesped = $this->huesped();
        $this->expectOutputRegex('/NUEVO DISPOSITIVO ENVIADO/');

        (new SendEmailNuevoDispositivo($huesped, '10.0.0.2', 'Windows', 'Chrome', 'Desktop'))->handle();

        Mail::assertSent(NuevoDispositivoEmail::class, fn ($correo) => $correo->hasTo($huesped->email));

        $control = QueueControl::latest('id')->firstOrFail();
        $this->assertSame(1, (int) $control->progreso);
        $this->assertNotNull($control->fecha_fin);
    }

    public function test_el_trabajo_de_errores_envia_un_correo_a_cada_administrador()
    {
        Mail::fake();
        $this->expectOutputRegex('/ERROR LOG ENVIADOS/');

        (new SendEmail(collect(['uno@test.com', 'dos@test.com']), $this->errorDePrueba()))->handle();

        Mail::assertSent(ErrorLogMail::class, 2);
        Mail::assertSent(ErrorLogMail::class, fn ($correo) => $correo->hasTo('dos@test.com'));
    }

    public function test_las_plantillas_de_correo_se_generan_sin_errores()
    {
        $error = $this->errorDePrueba();

        $this->assertStringContainsString('Fallo de prueba', (new ErrorLogMail($error))->render());
        $this->assertStringContainsString(
            '10.0.0.2',
            (new NuevoDispositivoEmail($this->huesped(), '10.0.0.2', 'Windows', 'Chrome', 'Desktop'))->render()
        );
    }
}
