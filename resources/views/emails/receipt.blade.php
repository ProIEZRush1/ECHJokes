@component('emails.layout')
<h1 style="margin:0 0 16px;font-size:22px;color:#ffffff;">¡Gracias por tu compra, {{ $name }}! 🙌</h1>

<p>Acabamos de acreditar tu plan <strong style="color:#39FF14;">{{ $planName }}</strong>.</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0;background:#0a0a0a;border:1px solid #262626;border-radius:10px;">
    <tr>
        <td style="padding:14px 18px;border-bottom:1px solid #262626;">
            <span style="color:#9ca3af;font-size:13px;">Plan</span>
            <br><span style="color:#ffffff;font-weight:600;">{{ $planName }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px;border-bottom:1px solid #262626;">
            <span style="color:#9ca3af;font-size:13px;">Llamadas incluidas</span>
            <br><span style="color:#ffffff;font-weight:600;">{{ $callsIncluded }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px;border-bottom:1px solid #262626;">
            <span style="color:#9ca3af;font-size:13px;">Créditos totales ahora</span>
            <br><span style="color:#39FF14;font-weight:700;font-size:22px;font-family:'SFMono-Regular',monospace;">{{ $totalCreditsNow }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 18px;">
            <span style="color:#9ca3af;font-size:13px;">Total cobrado</span>
            <br><span style="color:#ffffff;font-weight:600;">${{ number_format($amountMxn, 2) }} MXN</span>
        </td>
    </tr>
</table>

<div style="text-align:center;margin:24px 0 8px;">
    <a href="https://vacilada.com/dashboard" style="display:inline-block;padding:12px 28px;background:#39FF14;color:#0a0a0a;font-weight:700;text-decoration:none;border-radius:10px;">Ir al panel</a>
</div>

<p style="color:#9ca3af;font-size:12px;margin-top:20px;">
    Si la broma no se conecta (no contesta, ocupado, buzón de voz o error de línea), el crédito se reembolsa automáticamente a tu cuenta.
</p>
@endcomponent
