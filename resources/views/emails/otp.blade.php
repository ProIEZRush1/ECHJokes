@component('emails.layout')
<h1 style="margin:0 0 16px;font-size:22px;color:#ffffff;">Hola, {{ $name }} 👋</h1>

<p>Para terminar de crear tu cuenta en Vacilada, ingresa este código en la ventana donde empezaste:</p>

<div style="text-align:center;margin:28px 0;">
    <div style="display:inline-block;padding:20px 32px;background:#0a0a0a;border:2px solid #39FF14;border-radius:12px;font-family:'SFMono-Regular',Consolas,Menlo,monospace;font-size:34px;font-weight:700;letter-spacing:0.4em;color:#39FF14;">
        {{ $code }}
    </div>
</div>

<p style="color:#9ca3af;font-size:13px;">El código vence en <strong>10 minutos</strong>. Si tú no iniciaste este registro, puedes ignorar este correo.</p>
@endcomponent
