@component('mail::message')
# Ha ocurrido un error <br><br>

## Controller: {{$errorLog->controller}}<br><br>
<b>Mensaje:</b> {{$errorLog->mensaje}}<br><br>
<b>Parametros:</b> {{ $errorLog->parametros }}<br><br>
<b>Usuario:</b> {{ $errorLog->user->name ?? '(Sin usuario)' }}<br><br>

Fecha: {{ \Carbon\Carbon::parse($errorLog->created_at)->format('Y-m-d') }}<br>
Hora: {{ \Carbon\Carbon::parse($errorLog->created_at)->format('h:i:s A') }}<br>

@component('mail::button', ['url' => env('APP_URL').'/error_log/'.$errorLog->id])
Dar seguimiento
@endcomponent

{{ config('app.name') }}
@endcomponent
