<template>
  <div class="min-h-screen px-4 py-8 md:py-12">
    <div class="max-w-2xl mx-auto">
      <router-link to="/" class="text-sm text-gray-500 hover:text-neon">&larr; Volver al inicio</router-link>
      <h1 class="text-3xl md:text-4xl font-bold font-mono text-neon mt-4 mb-2">Términos y condiciones</h1>
      <p class="text-xs text-gray-500 mb-8">Última actualización: 21 de abril de 2026</p>

      <div class="prose prose-invert max-w-none text-gray-300 space-y-5 text-sm leading-relaxed">
        <section>
          <h2 class="text-lg font-semibold text-white">1. Qué es Vacilada</h2>
          <p>Vacilada (vacilada.com) es una plataforma de entretenimiento que permite enviar llamadas telefónicas generadas con inteligencia artificial a personas que el usuario conoce, con fines de broma inocente y consensuada. El servicio lo opera el titular del dominio vacilada.com.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">2. Aceptación del contrato</h2>
          <p>Al crear una cuenta, comprar créditos o enviar una llamada, el usuario declara tener al menos 18 años y aceptar estos términos. Si no estás de acuerdo, no uses el servicio.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">3. Uso responsable</h2>
          <p>El usuario es el único responsable del contenido que describe y del consentimiento implícito o explícito de la persona que recibe la llamada. Al enviar una broma, el usuario declara que:</p>
          <ul class="list-disc pl-5 space-y-1">
            <li>Conoce a la persona que recibirá la llamada y tiene una relación personal con ella.</li>
            <li>La persona está en condiciones de recibir una broma ligera sin sufrir daño psicológico, físico o material.</li>
            <li>No usará Vacilada para acosar, amenazar, extorsionar, engañar ni defraudar a nadie.</li>
          </ul>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">4. Contenido prohibido</h2>
          <p>Está terminantemente prohibido usar Vacilada para generar llamadas que:</p>
          <ul class="list-disc pl-5 space-y-1">
            <li>amenacen con violencia, muerte, secuestro o daño a la víctima o a sus familiares;</li>
            <li>simulen extorsión, cobro de piso, derecho de piso o exijan dinero bajo amenaza;</li>
            <li>suplanten a cárteles, narcos, sicarios o miembros del crimen organizado;</li>
            <li>simulen emergencias reales (familiar accidentado, detenido, hospitalizado o muerto);</li>
            <li>soliciten datos sensibles de la víctima (tarjetas, NIP, CVV, contraseñas, OTP);</li>
            <li>contengan contenido sexual, racista, homofóbico o de acoso;</li>
            <li>promuevan autolesión, suicidio u otras conductas peligrosas;</li>
            <li>violen cualquier ley aplicable en México o en el país de la persona que recibe la llamada.</li>
          </ul>
          <p>Vacilada usa moderación automática con IA y moderadores humanos para bloquear este tipo de escenarios. El usuario que intente evadir la moderación puede perder su cuenta, créditos y acceso al servicio sin reembolso.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">5. Créditos y pagos</h2>
          <p>Los créditos se compran por adelantado a través de Stripe. Un crédito equivale a un intento de llamada. Una llamada fallida (no contestada, ocupada, buzón de voz, error del operador) se reembolsa automáticamente al crédito del usuario. Una llamada completada consume el crédito, independientemente del resultado.</p>
          <p>Los créditos no son reembolsables en efectivo. Los créditos no tienen fecha de vencimiento mientras la cuenta esté activa. Vacilada se reserva el derecho de ajustar precios y cuotas gratuitas en cualquier momento con aviso previo en el sitio.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">6. Referidos</h2>
          <p>Cuando un usuario invitado hace su primera llamada pagada, el invitador recibe créditos gratis como incentivo. Vacilada puede cancelar créditos otorgados por referidos si detecta abuso (cuentas duplicadas, mismo dispositivo, auto-referidos).</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">7. Propiedad intelectual</h2>
          <p>Las grabaciones y transcripciones generadas pertenecen al usuario que las creó, siempre y cuando cumpla con estos términos. Vacilada conserva una licencia limitada para procesar, almacenar y mostrar el contenido como parte del servicio.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">8. Limitación de responsabilidad</h2>
          <p>Vacilada se ofrece "tal cual". No garantizamos disponibilidad 100%, calidad específica de audio o resultado particular de cada llamada. La responsabilidad por cualquier consecuencia de una broma recae exclusivamente en el usuario que la envió.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">9. Terminación</h2>
          <p>Podemos suspender o cerrar cuentas que violen estos términos, abusen del sistema de referidos, intenten evadir la moderación o reciban reportes creíbles de la persona que recibió la llamada.</p>
        </section>

        <section>
          <h2 class="text-lg font-semibold text-white">10. Ley aplicable y contacto</h2>
          <p>Estos términos se rigen por las leyes de México. Para cualquier duda, reporte o solicitud, escribe a <a href="mailto:soporte@vacilada.com" class="text-neon hover:underline">soporte@vacilada.com</a>.</p>
        </section>
      </div>
    </div>
  </div>
</template>
