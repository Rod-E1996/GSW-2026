<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    /*
    | Pasarela de pago del anticipo. Por ahora solo existe 'sandbox': una pasarela
    | de pruebas interna que no mueve dinero. La firma protege la notificacion
    | de resultado; sin PASARELA_SECRETO se deriva de APP_KEY.
    */
    'pasarela' => [
        'driver' => env('PASARELA_DRIVER', 'sandbox'),
        'secreto' => env('PASARELA_SECRETO'),
        //Minutos que se reutiliza un intento de pago abierto antes de crear otro
        'intento_minutos' => (int) env('PASARELA_INTENTO_MINUTOS', 30),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
