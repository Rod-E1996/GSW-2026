<!DOCTYPE html>
<html lang="es">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Restablecer Contraseña</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, 'Segoe UI', Arial, sans-serif; background-color: #f4f5f7; margin: 0; padding: 0; -webkit-text-size-adjust: none; color: #1e293b; }
        table { border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%; }

        .wrapper { width: 100%; table-layout: fixed; background-color: #f4f5f7; padding: 40px 15px; }
        .main { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 580px; border-radius: 20px; overflow: hidden; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); border: 1px solid #f1f5f9; }

        .header { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 40px 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px; }

        .content { padding: 35px 40px 25px 40px; text-align: center; line-height: 1.8; font-size: 16px; }
        .content p { margin: 0 0 20px; }

        .greeting { font-size: 22px; font-weight: 600; color: #0f172a; margin-bottom: 12px; }
        .subtitle { color: #64748b; font-size: 16px; margin-bottom: 40px; }

        .btn-wrapper { width: 100%; margin: 25px 0; }
        .btn { background-color: #3b82f6; background-image: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff !important; text-decoration: none; padding: 16px 36px; border-radius: 50px; font-weight: bold; display: inline-block; font-size: 16px; letter-spacing: 0.5px; box-shadow: 0 8px 15px rgba(37, 99, 235, 0.3); transition: all 0.3s ease; }

        .info-box { background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 22px 25px; text-align: left; border-radius: 0 12px 12px 0; margin-top: 40px; }
        .info-box p { margin: 0; font-size: 14px; color: #475569; line-height: 1.6; }

        .footer { padding: 35px 40px; text-align: center; background-color: #f8fafc; border-top: 1px solid #e2e8f0; }
        .footer p { margin: 0; font-size: 13px; color: #94a3b8; }

        .raw-link { font-size: 13px; color: #cbd5e1; word-break: break-all; margin-top: 20px; text-align: left; line-height: 1.5; border-top: 1px solid #f1f5f9; padding-top: 20px; }
        .raw-link a { color: #64748b; text-decoration: none; font-weight: 600; }

        @media only screen and (max-width: 600px) {
            .wrapper { padding: 30px 20px !important; }
            .content { padding: 30px 20px !important; }
            .header { padding: 30px 20px !important; }
            .footer { padding: 25px 20px !important; }
            .btn { padding: 14px 20px !important; display: block !important; margin: 0 auto !important; width: auto !important; }
            .greeting { font-size: 20px !important; }
        }
    </style>
</head>
<body>
    <table class="wrapper" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table class="main" cellpadding="0" cellspacing="0" role="presentation">

                    <!-- HEADER -->
                    <tr>
                        <td class="header">
                            <h1>Cambio de Contraseña</h1>
                        </td>
                    </tr>

                    <!-- BODY CONTENT -->
                    <tr>
                        <td class="content">
                            <h2 class="greeting">Hola, {{ $user->name ?? 'Usuario' }}</h2>
                            <p class="subtitle">Hemos recibido una solicitud para establecer una nueva contraseña en tu cuenta de <strong>{{ config('app.name') }}</strong>.</p>

                            <table class="btn-wrapper" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $url }}" class="btn">Confirmar nueva contraseña</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #64748b; font-size: 15px;">Si no solicitaste este cambio, no te preocupes. Tu cuenta está a salvo y puedes ignorar este correo sin hacer nada.</p>

                            <div class="info-box">
                                <p><strong>Nota de seguridad:</strong> Este enlace especial tiene una validez única de {{ config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60) }} minutos. Transcurrido este tiempo deberás emitir una nueva solicitud.</p>
                            </div>

                            <div class="raw-link">
                                <span style="color: #94a3b8;">¿Tienes problemas técnicos con el botón? Copia este enlace en tu navegador:</span><br><br>
                                <a href="{{ $url }}">{{ $url }}</a>
                            </div>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td class="footer">
                            <p>&copy; {{ date('Y') }} <strong>{{ config('app.name') }}</strong>. Todos los derechos reservados.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
