@component('emails.layout')
<h1 style="margin:0 0 16px;font-size:22px;color:#ffffff;">¡Listo, {{ $name }}! 🎉</h1>

<p>Tu cuenta en Vacilada quedó activada. Ya puedes hacer bromas telefónicas generadas con IA desde tu panel.</p>

@if ($credits > 0)
<div style="margin:20px 0;padding:16px;background:#0a0a0a;border:1px solid #39FF14;border-radius:10px;text-align:center;">
    <p style="margin:0;color:#9ca3af;font-size:12px;text-transform:uppercase;letter-spacing:0.1em;">Créditos disponibles</p>
    <p style="margin:4px 0 0;color:#39FF14;font-size:32px;font-weight:700;font-family:'SFMono-Regular',monospace;">{{ $credits }}</p>
</div>
@else
<p>Para hacer tu primera broma, puedes:</p>
<ul>
    <li><strong>Compartir tu link de referido</strong> y ganar 2 créditos cuando un amigo use Vacilada.</li>
    <li><strong>Comprar un plan</strong> desde $29 MXN con más créditos y minutos.</li>
</ul>
@endif

@if ($referralCode)
<p style="margin-top:20px;">Tu link personal de referidos:</p>
<p style="margin:8px 0;"><a href="https://vacilada.com/?ref={{ $referralCode }}" style="color:#39FF14;font-family:'SFMono-Regular',monospace;">vacilada.com/?ref={{ $referralCode }}</a></p>
@endif

<div style="text-align:center;margin:28px 0 8px;">
    <a href="https://vacilada.com/dashboard" style="display:inline-block;padding:12px 28px;background:#39FF14;color:#0a0a0a;font-weight:700;text-decoration:none;border-radius:10px;">Hacer una broma</a>
</div>
@endcomponent
