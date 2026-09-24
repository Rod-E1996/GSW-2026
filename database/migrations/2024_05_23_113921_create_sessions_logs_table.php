<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSessionsLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sessions_logs', function (Blueprint $table) {
            $table->id();

            //Llaves foraneas
            $table->foreignIdFor(\App\Models\User::class)->references('id')->on('users');

            $table->text('session_id')->nullable();
            $table->string('ip_address', 250)->nullable();
            $table->string('device', 250)->nullable();
            $table->string('platform', 250)->nullable();
            $table->string('browser', 250)->nullable();
            $table->string('device_type', 250)->nullable();
            $table->string('device_model', 250)->nullable();
            $table->integer('estado')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sessions_logs');
    }
}
