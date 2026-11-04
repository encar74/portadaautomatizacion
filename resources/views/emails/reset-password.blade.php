<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Restablece tu contraseña · Portada.info</title></head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background-color:#ffffff;border-radius:16px;">
<tr><td style="padding:32px;">
<img src="https://prensa.portada.info/images/logo-portada.png" alt="Portada.info" width="224" style="display:block;width:224px;max-width:100%;height:auto;border:0;">
<h1 style="margin:32px 0 20px;font-size:24px;line-height:32px;">Restablece tu contraseña</h1>
<p style="font-size:16px;line-height:24px;">Hola:</p>
<p style="font-size:16px;line-height:24px;">Hemos recibido una solicitud para restablecer la contraseña de tu cuenta de Portada.info.</p>
<p style="margin:28px 0;"><a href="{{ $resetUrl }}" style="display:inline-block;background-color:#bd1b42;color:#ffffff;text-decoration:none;font-size:16px;font-weight:bold;padding:14px 22px;border-radius:8px;">Restablecer contraseña</a></p>
<p style="font-size:14px;line-height:22px;">Este enlace caduca en {{ $expires }} minutos.</p>
<p style="font-size:14px;line-height:22px;">Si no has solicitado este cambio, puedes ignorar este correo. Tu contraseña seguirá siendo la misma.</p>
<p style="margin-top:28px;font-size:14px;line-height:22px;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
<p style="font-size:12px;line-height:20px;word-break:break-all;"><a href="{{ $resetUrl }}" style="color:#a61538;">{{ $resetUrl }}</a></p>
<p style="margin-top:28px;font-size:14px;">El equipo de Portada.info</p>
</td></tr></table>
</td></tr></table>
</body></html>
