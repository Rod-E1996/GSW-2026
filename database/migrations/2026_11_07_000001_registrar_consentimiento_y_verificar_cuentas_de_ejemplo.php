<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    //Cuentas de ejemplo que crean los seeders
    private const CUENTAS_DE_EJEMPLO = ['admin@gmail.com', 'recepcion@hotellink.com', 'huesped@hotellink.com'];

    /**
     * La Ley de Proteccion de Datos Personales pide poder demostrar el consentimiento,
     * asi que se guarda cuando el huesped acepta el aviso de privacidad.
     * Desde ahora reservar exige el correo verificado: las cuentas de ejemplo se dan por verificadas.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacidad_aceptada_at')->nullable()->after('email_verified_at');
        });

        DB::table('users')
            ->whereIn('email', self::CUENTAS_DE_EJEMPLO)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('privacidad_aceptada_at');
        });
    }
};
