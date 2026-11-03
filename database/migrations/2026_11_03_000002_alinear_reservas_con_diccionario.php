<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lleva reservas al diccionario de datos 3.11: los datos del huesped pasan a
     * la tabla huespedes y se agregan los desgloses y marcas de tiempo.
     * Conserva las reservas que ya existan.
     */
    public function up()
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->unsignedBigInteger('huesped_id')->nullable()->after('codigo');
            $table->string('canal', 20)->nullable()->after('hora_estimada_llegada');
            $table->decimal('subtotal_alojamiento', 10, 2)->default(0)->after('estado_reserva');
            $table->decimal('subtotal_servicios', 10, 2)->default(0)->after('subtotal_alojamiento');
            $table->decimal('descuento', 10, 2)->nullable()->default(0)->after('subtotal_servicios');
            $table->decimal('impuesto', 10, 2)->nullable()->default(0)->after('descuento');
            $table->decimal('anticipo_requerido', 10, 2)->nullable()->after('total');
            $table->text('notas')->nullable()->after('anticipo_requerido');
            $table->dateTime('check_in_at')->nullable()->after('notas');
            $table->dateTime('check_out_at')->nullable()->after('check_in_at');
            $table->dateTime('cancelada_at')->nullable()->after('check_out_at');
            $table->string('motivo_cancelacion', 255)->nullable()->after('cancelada_at');
        });

        $this->trasladarDatos();

        Schema::table('reservas', function (Blueprint $table) {
            $table->unsignedBigInteger('huesped_id')->nullable(false)->change();
            $table->foreign('huesped_id')->references('id')->on('huespedes');
            $table->dropColumn(['huesped_nombre', 'huesped_documento', 'huesped_email', 'huesped_telefono']);
        });
    }

    private function trasladarDatos(): void
    {
        //Solo se enlaza con cuentas de correo verificado: un registro sin verificar no
        //demuestra que la persona sea la duena de ese correo.
        $cuentas = DB::table('users')->whereNotNull('email_verified_at')
            ->pluck('id', 'email')
            ->mapWithKeys(fn ($id, $email) => [mb_strtolower($email) => $id]);

        $huespedes = [];

        foreach (DB::table('reservas')->orderBy('id')->get() as $reserva) {
            $correo = mb_strtolower(trim($reserva->huesped_email));

            if (!isset($huespedes[$correo])) {
                $partes = preg_split('/\s+/', trim($reserva->huesped_nombre), 2);

                $huespedes[$correo] = DB::table('huespedes')->insertGetId([
                    'user_id' => $cuentas[$correo] ?? null,
                    'nombres' => $partes[0],
                    'apellidos' => $partes[1] ?? '',
                    'documento' => $reserva->huesped_documento,
                    'email' => $reserva->huesped_email,
                    'telefono' => $reserva->huesped_telefono,
                    'created_at' => $reserva->created_at,
                    'updated_at' => $reserva->updated_at,
                ]);
            }

            $servicios = (float) DB::table('reserva_servicio')->where('reserva_id', $reserva->id)->sum('subtotal');

            DB::table('reservas')->where('id', $reserva->id)->update([
                'huesped_id' => $huespedes[$correo],
                'subtotal_servicios' => $servicios,
                'subtotal_alojamiento' => (float) $reserva->total - $servicios,
                // 3 = en estadia, 4 = finalizada, 5 = cancelada
                'check_in_at' => in_array((int) $reserva->estado_reserva, [3, 4]) ? $reserva->fecha_llegada : null,
                'check_out_at' => (int) $reserva->estado_reserva === 4 ? $reserva->fecha_salida : null,
                'cancelada_at' => (int) $reserva->estado_reserva === 5 ? $reserva->updated_at : null,
            ]);
        }

        //Las cuentas de huesped existentes tambien necesitan su ficha
        $conFicha = DB::table('huespedes')->whereNotNull('user_id')->pluck('user_id');
        $cuentasHuesped = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', \App\Models\User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'Huésped')
            ->whereNotIn('users.id', $conFicha)
            ->select('users.id', 'users.name', 'users.email', 'users.phone', 'users.created_at', 'users.updated_at')
            ->get();

        foreach ($cuentasHuesped as $cuenta) {
            $partes = preg_split('/\s+/', trim($cuenta->name), 2);

            DB::table('huespedes')->insert([
                'user_id' => $cuenta->id,
                'nombres' => $partes[0],
                'apellidos' => $partes[1] ?? '',
                'email' => $cuenta->email,
                'telefono' => $cuenta->phone,
                'created_at' => $cuenta->created_at,
                'updated_at' => $cuenta->updated_at,
            ]);
        }
    }

    public function down()
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->string('huesped_nombre', 150)->default('')->after('ninos');
            $table->string('huesped_documento', 50)->nullable()->after('huesped_nombre');
            $table->string('huesped_email', 150)->default('')->after('huesped_documento');
            $table->string('huesped_telefono', 30)->nullable()->after('huesped_email');
        });

        foreach (DB::table('reservas')->orderBy('id')->get() as $reserva) {
            $huesped = DB::table('huespedes')->find($reserva->huesped_id);

            DB::table('reservas')->where('id', $reserva->id)->update([
                'huesped_nombre' => trim($huesped->nombres . ' ' . $huesped->apellidos),
                'huesped_documento' => $huesped->documento,
                'huesped_email' => (string) $huesped->email,
                'huesped_telefono' => $huesped->telefono,
            ]);
        }

        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['huesped_id']);
            $table->dropColumn([
                'huesped_id', 'canal', 'subtotal_alojamiento', 'subtotal_servicios', 'descuento', 'impuesto',
                'anticipo_requerido', 'notas', 'check_in_at', 'check_out_at', 'cancelada_at', 'motivo_cancelacion',
            ]);
        });

        //Las fichas salen de las reservas o de las cuentas; se recrean al volver a migrar
        DB::table('huespedes')->delete();
    }
};
