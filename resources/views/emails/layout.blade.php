<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Vacilada' }}</title>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#e5e5e5;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#0a0a0a;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:480px;background:#141414;border:1px solid #262626;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px 12px;border-bottom:1px solid #262626;">
                            <a href="https://vacilada.com" style="color:#39FF14;text-decoration:none;font-weight:700;font-size:22px;font-family:'SFMono-Regular',Consolas,Menlo,monospace;">Vacilada</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;color:#e5e5e5;font-size:15px;line-height:1.6;">
                            {!! $slot !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px;border-top:1px solid #262626;font-size:11px;color:#6b7280;">
                            Bromas telefónicas con IA · <a href="https://vacilada.com" style="color:#39FF14;text-decoration:none;">vacilada.com</a><br>
                            <a href="https://vacilada.com/terms" style="color:#6b7280;">Términos</a> ·
                            <a href="https://vacilada.com/privacy" style="color:#6b7280;">Privacidad</a> ·
                            <a href="mailto:soporte@vacilada.com" style="color:#6b7280;">Soporte</a>
                        </td>
                    </tr>
                </table>
                <p style="color:#4b5563;font-size:10px;margin-top:16px;">© 2026 Vacilada. Sólo para bromas consensuadas entre amigos.</p>
            </td>
        </tr>
    </table>
</body>
</html>
