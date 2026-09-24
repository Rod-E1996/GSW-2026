<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\NuevoDispositivoEmail;
use App\Models\QueueControl;
use Carbon\Carbon;

class SendEmailNuevoDispositivo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $ip_address;
    protected $platform;
    protected $browser;
    protected $deviceType;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $ip_address, $platform, $browser, $deviceType)
    {
        $this->user = $user;
        $this->ip_address = $ip_address;
        $this->platform = $platform;
        $this->browser = $browser;
        $this->deviceType = $deviceType;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $queue_control = new QueueControl();
        $queue_control->titulo = 'Notificación de nuevo dispositivo conectado';
        $queue_control->total_procesos = 1;
        $queue_control->progreso = 0;
        $queue_control->fecha_inicio = Carbon::now()->toDateTimeString();
        $queue_control->user_id = $this->user->id ?? null;
        $queue_control->save();

        \Mail::to($this->user->email)->send(new NuevoDispositivoEmail($this->user, $this->ip_address, $this->platform, $this->browser, $this->deviceType));
        $queue_control->increment('progreso');
        $queue_control->save();

        $queue_control->pendiente = 0;
        $queue_control->fecha_fin = Carbon::now()->toDateTimeString();
        $queue_control->save();

        echo "\nEMAIL DE NOTIFICACION DE NUEVO DISPOSITIVO ENVIADO\n";
    }
}
