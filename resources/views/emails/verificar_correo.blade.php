<!DOCTYPE html>
<html lang="es">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Verifica tu correo</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, 'Segoe UI', Arial, sans-serif; background-color: #f4f5f7; margin: 0; padding: 0; color: #1e293b; }
        .wrapper { width: 100%; background-color: #f4f5f7; padding: 40px 15px; }
        .main { background-color: #ffffff; margin: 0 auto; max-width: 580px; border-radius: 20px; overflow: hidden; border: 1px solid #f1f5f9; }
        .header { background: #0b4f57; padding: 32px 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; }
        .content { padding: 32px 40px; line-height: 1.7; font-size: 16px; }
        .btn { background-color: #d98a1c; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 50px; font-weight: bold; display: inline-block; }
        .nota { font-size: 13px; color: #64748b; word-break: break-all; }
        .footer { padding: 24px 40px; text-align: center; background-color: #f8fafc; font-size: 13px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="wrapper" cellpadding="0" cellspacing="0" role="presentation" width="100%">
        <tr>
            <td align="center">
                <table class="main" cellpadding="0" cellspacing="0" role="presentation" width="100%">
                    <tr><td class="header"><h1>Verifica tu correo</h1></td></tr>
                    <tr>
                        <td class="content">
                            <p>Hola, {{ $user->name }}:</p>
                            <p>Gracias por crear tu cuenta en {{ config('hotel.nombre') }}. Confirma que este correo es tuyo para poder reservar.</p>
                            <p style="text-align:center"><a class="btn" href="{{ $url }}">Verificar mi correo</a></p>
                            <p>El enlace vence en {{ config('auth.verification.expire', 60) }} minutos. Si no creaste esta cuenta, ignora este mensaje.</p>
                            <p class="nota">Si el botón no funciona, copia esta dirección en tu navegador:<br>{{ $url }}</p>
                        </td>
                    </tr>
                    <tr><td class="footer">{{ config('hotel.nombre') }} · {{ config('hotel.direccion') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
