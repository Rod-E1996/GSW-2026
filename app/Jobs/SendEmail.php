<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\ErrorLogMail;
use App\Models\QueueControl;
use Carbon\Carbon;

class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $correosAdmin;
    protected $errorLog;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($correosAdmin, $errorLog)
    {
        $this->correosAdmin = $correosAdmin;
        $this->errorLog = $errorLog;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $queue_control = new QueueControl();
        $queue_control->titulo = 'Envio de error log a los administradores por email';
        $queue_control->total_procesos = count($this->correosAdmin);
        $queue_control->progreso = 0;
        $queue_control->fecha_inicio = Carbon::now()->toDateTimeString();
        $queue_control->user_id = $this->errorLog->user_id ?? null;
        $queue_control->save();

        foreach($this->correosAdmin AS $val){
            \Mail::to($val)->send(new ErrorLogMail($this->errorLog));
            $queue_control->increment('progreso');
            $queue_control->save();
        }

        $queue_control->pendiente = 0;
        $queue_control->fecha_fin = Carbon::now()->toDateTimeString();
        $queue_control->save();

        echo "\nEMAILS DEL ERROR LOG ENVIADOS\n";
    }
}
