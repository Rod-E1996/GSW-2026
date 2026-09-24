<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NuevoDispositivoEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $ip_address;
    public $platform;
    public $browser;
    public $deviceType;

    /**
     * Create a new message instance.
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
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('APP_NAME'))
                    ->view('emails.nuevoDispositivo')
                    ->subject('Nuevo dispositivo conectado');
    }
}
