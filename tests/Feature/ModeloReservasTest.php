<?php

namespace Tests\Feature;

use App\Models\Huesped;
use App\Models\Reserva;
use App\Models\ReservaHistorial;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\ConfiguracionesSeeder;
use Database\Seeders\PermisosHotelSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifica el nucleo de datos de reservas contra el diccionario de datos del informe.
 */
class ModeloReservasTest extends TestCase
{
    use DatabaseTransactions;

    //Columnas de las secciones 3.2 a 3.14 y 3.21 del diccionario de datos
    public static function diccionario(): array
    {
        return [
            'tipos_habitacion' => ['tipos_habitacion', [
                'id', 'nombre', 'nombre_en', 'slug', 'capacidad', 'precio_base', 'descripcion', 'descripcion_en',
                'orden', 'estado', 'created_at', 'updated_at',
            ]],
            'habitaciones' => ['habitaciones', [
                'id', 'numero', 'piso', 'estado_habitacion', 'descripcion', 'tipo_habitacion_id', 'estado',
                'created_at', 'updated_at',
            ]],
            'tipo_habitacion_imagenes' => ['tipo_habitacion_imagenes', [
                'id', 'ruta', 'nombre_original', 'orden', 'principal', 'tipo_habitacion_id', 'created_at', 'updated_at',
            ]],
            'temporadas' => ['temporadas', [
                'id', 'nombre', 'nombre_en', 'fecha_inicio', 'fecha_fin', 'multiplicador', 'prioridad', 'color',
                'estado', 'created_at', 'updated_at',
            ]],
            'tarifas' => ['tarifas', [
                'id', 'tipo_habitacion_id', 'temporada_id', 'precio_noche', 'estado', 'created_at', 'updated_at',
            ]],
            'bloqueos_habitacion' => ['bloqueos_habitacion', [
                'id', 'habitacion_id', 'fecha_inicio', 'fecha_fin', 'motivo', 'descripcion', 'user_id', 'estado',
                'created_at', 'updated_at',
            ]],
            'categorias_servicio' => ['categorias_servicio', [
                'id', 'nombre', 'nombre_en', 'icono', 'orden', 'estado', 'created_at', 'updated_at',
            ]],
            'servicios' => ['servicios', [
                'id', 'categoria_servicio_id', 'nombre', 'nombre_en', 'descripcion', 'descripcion_en', 'icono', 'imagen',
                'precio', 'forma_cobro', 'requiere_fecha', 'estado', 'created_at', 'updated_at',
            ]],
            'huespedes' => ['huespedes', [
                'id', 'user_id', 'nombres', 'apellidos', 'tipo_documento', 'documento', 'email', 'telefono',
                'nacionalidad', 'fecha_nacimiento', 'direccion', 'notas', 'estado', 'created_at', 'updated_at',
            ]],
            'reservas' => ['reservas', [
                'id', 'codigo', 'huesped_id', 'tipo_habitacion_id', 'habitacion_id', 'fecha_llegada', 'fecha_salida',
                'adultos', 'ninos', 'hora_estimada_llegada', 'canal', 'estado_reserva', 'subtotal_alojamiento',
                'subtotal_servicios', 'descuento', 'impuesto', 'total', 'anticipo_requerido', 'notas', 'check_in_at',
                'check_out_at', 'cancelada_at', 'motivo_cancelacion', 'user_id', 'estado', 'created_at', 'updated_at',
            ]],
            'reserva_noches' => ['reserva_noches', [
                'id', 'reserva_id', 'habitacion_id', 'fecha', 'temporada_id', 'precio_noche', 'created_at', 'updated_at',
            ]],
            'reserva_servicio' => ['reserva_servicio', [
                'id', 'reserva_id', 'servicio_id', 'cantidad', 'precio_unitario', 'subtotal', 'fecha_servicio',
                'hora_servicio', 'estado_servicio', 'notas', 'created_at', 'updated_at',
            ]],
            'reserva_historial' => ['reserva_historial', [
                'id', 'reserva_id', 'estado_anterior', 'estado_nuevo', 'comentario', 'user_id', 'created_at',
            ]],
            'configuraciones' => ['configuraciones', [
                'id', 'clave', 'valor', 'tipo', 'grupo', 'descripcion', 'created_at', 'updated_at',
            ]],
        ];
    }

    #[DataProvider('diccionario')]
    public function test_las_tablas_coinciden_con_el_diccionario(string $tabla, array $columnas)
    {
        $this->assertEqualsCanonicalizing($columnas, Schema::getColumnListing($tabla));
    }

    public function test_los_estados_usan_los_nombres_del_informe()
    {
        $nombres = array_column(Reserva::ESTADOS, 'nombre');

        $this->assertSame(['Pendiente', 'Confirmada', 'En estadía', 'Finalizada', 'Cancelada'], $nombres);
    }

    private function reservaDePrueba(array $datos = []): Reserva
    {
        $base = Reserva::with('huesped')->firstOrFail();

        return Reserva::create(array_merge([
            'codigo' => 'HL-PRUEBA-' . uniqid(),
            'huesped_id' => $base->huesped_id,
            'tipo_habitacion_id' => $base->tipo_habitacion_id,
            'fecha_llegada' => now()->addDays(40)->toDateString(),
            'fecha_salida' => now()->addDays(42)->toDateString(),
            'adultos' => 1,
            'estado_reserva' => Reserva::PENDIENTE,
        ], $datos));
    }

    public function test_crear_una_reserva_abre_su_historial()
    {
        $reserva = $this->reservaDePrueba();

        $this->assertCount(1, $reserva->historial);
        $this->assertNull($reserva->historial[0]->estado_anterior);
        $this->assertSame(Reserva::PENDIENTE, (int) $reserva->historial[0]->estado_nuevo);
    }

    public function test_cada_cambio_de_estado_queda_en_el_historial_con_su_autor()
    {
        $reserva = $this->reservaDePrueba();
        $recepcion = User::where('email', 'recepcion@hotellink.com')->firstOrFail();
        $this->actingAs($recepcion);

        $reserva->update(['estado_reserva' => Reserva::CONFIRMADA]);

        $ultimo = ReservaHistorial::where('reserva_id', $reserva->id)->latest('id')->first();
        $this->assertSame(Reserva::PENDIENTE, (int) $ultimo->estado_anterior);
        $this->assertSame(Reserva::CONFIRMADA, (int) $ultimo->estado_nuevo);
        $this->assertSame($recepcion->id, (int) $ultimo->user_id);
    }

    public function test_editar_otros_datos_no_agrega_historial()
    {
        $reserva = $this->reservaDePrueba();

        $reserva->update(['notas' => 'Llega tarde']);

        $this->assertSame(1, ReservaHistorial::where('reserva_id', $reserva->id)->count());
    }

    public function test_los_datos_de_ejemplo_tienen_importes_coherentes()
    {
        foreach (Reserva::with('huesped')->get() as $reserva) {
            $this->assertNotNull($reserva->huesped);
            $this->assertEquals(
                (float) $reserva->total,
                round($reserva->subtotal_alojamiento + $reserva->subtotal_servicios - $reserva->descuento + $reserva->impuesto, 2),
                $reserva->codigo
            );
            $this->assertEquals(
                round($reserva->total * 0.30, 2),
                (float) $reserva->anticipo_requerido,
                $reserva->codigo
            );
        }
    }

    public function test_la_reserva_en_estadia_tiene_una_noche_por_fecha_que_suma_el_alojamiento()
    {
        $reserva = Reserva::where('estado_reserva', Reserva::EN_ESTADIA)->firstOrFail();

        $this->assertNotNull($reserva->check_in_at);
        $this->assertCount($reserva->noches_total, $reserva->noches);
        $this->assertEquals((float) $reserva->subtotal_alojamiento, (float) $reserva->noches->sum('precio_noche'));
    }

    public function test_los_servicios_exponen_los_campos_nuevos_del_detalle()
    {
        $reserva = $this->reservaDePrueba();
        $servicio = Servicio::firstOrFail();

        $reserva->servicios()->attach($servicio->id, [
            'cantidad' => 1,
            'precio_unitario' => 10,
            'subtotal' => 10,
            'fecha_servicio' => now()->addDays(40)->toDateString(),
            'notas' => 'En la habitación',
        ]);

        $detalle = $reserva->fresh()->servicios->first()->pivot;
        $this->assertSame('En la habitación', $detalle->notas);
        $this->assertSame(1, (int) $detalle->estado_servicio);
    }

    public function test_el_registro_publico_crea_la_ficha_de_huesped()
    {
        $this->postJson('/api/register', [
            'nombres' => 'Ana María',
            'apellidos' => 'Pérez',
            'email' => 'ana.perez@test.com',
            'telefono' => '7000-1111',
            'password' => '12345678',
            'password_confirmation' => '12345678',
            'consentimiento' => true,
        ])->assertCreated();

        $ficha = User::where('email', 'ana.perez@test.com')->firstOrFail()->huesped;

        $this->assertSame('Ana María', $ficha->nombres);
        $this->assertSame('Pérez', $ficha->apellidos);
        $this->assertSame('70001111', $ficha->telefono);
        $this->assertSame('ana.perez@test.com', $ficha->email);
        $this->assertSame('Ana María Pérez', $ficha->nombre_completo);
    }

    public function test_el_registro_por_blade_tambien_crea_la_ficha()
    {
        $this->post('/register', [
            'name' => 'Blade Huésped',
            'email' => 'blade.huesped@test.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);

        $this->assertNotNull(User::where('email', 'blade.huesped@test.com')->firstOrFail()->huesped);
    }

    public function test_registrarse_con_un_correo_ajeno_no_da_acceso_a_sus_reservas()
    {
        $ficha = Reserva::firstOrFail()->huesped;

        $this->postJson('/api/register', [
            'nombres' => 'Persona',
            'apellidos' => 'Intrusa',
            'email' => $ficha->email,
            'telefono' => '70002222',
            'password' => '12345678',
            'password_confirmation' => '12345678',
            'consentimiento' => true,
        ])->assertCreated();

        $cuenta = User::where('email', $ficha->email)->firstOrFail();

        $this->assertNull($ficha->fresh()->user_id);
        $this->assertNotSame($ficha->id, $cuenta->huesped->id);
        $this->assertCount(0, $cuenta->huesped->reservas);
    }

    public function test_la_cuenta_de_huesped_de_prueba_tiene_ficha()
    {
        $this->assertNotNull(User::where('email', 'huesped@hotellink.com')->firstOrFail()->huesped);
    }

    public function test_paraUsuario_no_duplica_la_ficha()
    {
        $user = User::where('email', 'huesped@hotellink.com')->firstOrFail();

        $this->assertSame($user->huesped->id, Huesped::paraUsuario($user)->id);
    }

    public function test_los_permisos_nuevos_existen_y_se_asignan_por_rol()
    {
        $this->assertSame(
            count(PermisosHotelSeeder::$permisos),
            Permission::whereIn('name', PermisosHotelSeeder::$permisos)->count()
        );

        $admin = Role::where('name', 'Super Administrador')->firstOrFail();
        foreach (PermisosHotelSeeder::$permisos as $permiso) {
            $this->assertTrue($admin->hasPermissionTo($permiso), $permiso);
        }

        $recepcion = Role::where('name', 'Recepcionista')->firstOrFail();
        $this->assertTrue($recepcion->hasPermissionTo('reserva_checkin'));
        $this->assertTrue($recepcion->hasPermissionTo('huesped_store'));
        $this->assertTrue($recepcion->hasPermissionTo('servicio_index'));
        $this->assertFalse($recepcion->hasPermissionTo('servicio_store'));
        $this->assertFalse($recepcion->hasPermissionTo('configuracion_index'));
        $this->assertFalse($recepcion->hasPermissionTo('reporte_index'));

        $huesped = Role::where('name', 'Huésped')->firstOrFail();
        $this->assertFalse($huesped->hasPermissionTo('reserva_index'));
    }

    public function test_cada_opcion_del_menu_usa_un_permiso_que_existe()
    {
        $menu = file_get_contents(base_path('frontend/src/layouts/menu.js'));
        preg_match_all("/permiso: '([a-z_]+)'/", $menu, $coincidencias);

        $this->assertNotEmpty($coincidencias[1]);
        foreach ($coincidencias[1] as $permiso) {
            $this->assertTrue(Permission::where('name', $permiso)->exists(), "Falta el permiso $permiso");
        }
    }

    public function test_repetir_los_seeders_no_duplica_ni_pisa_los_valores()
    {
        \App\Models\Configuracion::where('clave', 'anticipo_porcentaje')->update(['valor' => '55']);
        $permisos = Permission::count();

        (new PermisosHotelSeeder)->run();
        (new ConfiguracionesSeeder)->run();

        $this->assertSame($permisos, Permission::count());
        $this->assertSame(55.0, \App\Models\Configuracion::valor('anticipo_porcentaje'));
    }
}
