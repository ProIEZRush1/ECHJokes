<template>
  <div class="min-h-screen px-4 py-8 md:py-12">
    <div class="max-w-2xl mx-auto">
      <router-link to="/" class="text-sm text-gray-500 hover:text-neon">&larr; Volver al inicio</router-link>
      <h1 class="text-3xl md:text-4xl font-bold font-mono text-neon mt-4 mb-2">Política de privacidad</h1>
      <p class="text-xs text-gray-500 mb-8">Última actualización: 21 de abril de 2026</p>

      <div class="prose prose-invert max-w-none text-gray-300 space-y-5 text-sm leading-relaxed">
        <section>
          <h2 class="text-lg font-semibold text-white">1. Qué datos recopilamos</h2>
          <ul class="list-disc pl-5 space-y-1">
            <li><strong>Cuenta</strong>: nombre, correo electrónico, contraseña cifrada, código de referido.</li>
            <li><strong>Pagos</strong>: Stripe procesa los pagos; Vacilada sólo guarda un identificador del cliente y los últimos 4 dígitos de la tarjeta para mostrar en tu panel. Nunca guardamos el número completo, CVV ni NIP.</li>
            <li><strong>Llamadas</strong>: número destino, escenario escrito por el usuario, duración, resultado (contestada, no contestada, buzón de voz) y grabación si Twilio la entrega.</li>
            <li><strong>Técnicos</strong>: dirección IP, user-agent, cookies de sesión y referidos, y eventos de uso necesarios para operar el servicio y prevenir fraude.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">2. Para qué los usamos</h2>
          <ul class="list-disc pl-5 space-y-1">
            <li>Procesar y realizar las llamadas que solicitas.</li>
            <li>Cobrar créditos y procesar reembolsos.</li>
            <li>Moderar contenido con IA y moderadores humanos para bloquear usos prohibidos.</li>
            <li>Detectar y prevenir abuso (cuentas duplicadas, spam, violaciones de los términos).</li>
            <li>Enviarte notificaciones operativas (estado de la llamada, recibo de compra).</li>
          </ul>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">3. Con quién compartimos</h2>
          <p>Usamos los siguientes procesadores de datos, todos con acuerdos de confidencialidad:</p>
          <ul class="list-disc pl-5 space-y-1">
            <li><strong>Twilio</strong> — realiza las llamadas telefónicas.</li>
            <li><strong>ElevenLabs</strong> — genera las voces sintéticas.</li>
            <li><strong>OpenAI</strong> — convierte audio a texto en tiempo real.</li>
            <li><strong>Anthropic</strong> — genera las respuestas del personaje y modera los escenarios.</li>
            <li><strong>Stripe</strong> — procesa los pagos con tarjeta.</li>
            <li><strong>Cloudflare</strong> — CDN y protección de red.</li>
          </ul>
          <p>No vendemos datos personales a terceros.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">4. Consentimiento de la persona que recibe la llamada</h2>
          <p>Vacilada no contacta directamente a la persona a la que se dirige la broma fuera del momento de la llamada. El usuario que envía la broma declara tener una relación personal con la persona y asumir la responsabilidad del consentimiento. Si eres una persona que recibió una llamada de Vacilada y quieres que borremos tu número, escribe a <a href="mailto:privacidad@vacilada.com" class="text-neon hover:underline">privacidad@vacilada.com</a> y lo haremos dentro de 48 horas.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">5. Retención</h2>
          <p>Los datos de cuenta se guardan mientras la cuenta esté activa. Las grabaciones y transcripciones se guardan 90 días por defecto, y se pueden eliminar a petición del usuario desde el panel. Los registros técnicos (logs) se retienen hasta 30 días.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">6. Tus derechos</h2>
          <p>Puedes solicitar acceso, corrección o eliminación de tus datos escribiendo a <a href="mailto:privacidad@vacilada.com" class="text-neon hover:underline">privacidad@vacilada.com</a>. Procesaremos la solicitud en un plazo máximo de 20 días hábiles conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (México).</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">7. Cookies</h2>
          <p>Usamos cookies de sesión para mantenerte conectado y una cookie de referido (<code>vacilada_ref</code>) para atribuir invitaciones. No usamos cookies de seguimiento publicitario.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">8. Cambios</h2>
          <p>Si actualizamos esta política, publicaremos la nueva versión aquí y actualizaremos la fecha al inicio. Los cambios sustanciales se notificarán por correo.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">9. Contacto</h2>
          <p><a href="mailto:privacidad@vacilada.com" class="text-neon hover:underline">privacidad@vacilada.com</a></p>
        </section>
      </div>
    </div>
  </div>
</template>
