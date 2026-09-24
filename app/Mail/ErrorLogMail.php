<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ErrorLogMail extends Mailable
{
    use Queueable, SerializesModels;

    public $errorLog;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($errorLog)
    {
        $this->errorLog = $errorLog;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('APP_NAME'))
        ->markdown('emails.errorLog')->subject('Ha ocurrido un error');
    }
}
