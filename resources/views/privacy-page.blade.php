<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de privacidad — Vacilada | Bromas telefónicas con IA</title>
    <meta name="description" content="Política de privacidad de Vacilada: qué datos recopilamos, cómo los usamos, con quién los compartimos y tus derechos." />
    <link rel="canonical" href="{{ url('/privacy') }}" />
    <meta property="og:title" content="Política de privacidad — Vacilada" />
    <meta property="og:description" content="Cómo protegemos tu información personal en Vacilada." />
    <meta property="og:image" content="{{ url('/brand/og-image.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7978976781623579" crossorigin="anonymous"></script>
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','2171220190087137');fbq('track','PageView');</script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=2171220190087137&ev=PageView&noscript=1"/></noscript>
</head>
<body class="bg-matrix-900 text-white min-h-screen antialiased">
    <nav class="px-4 py-4 border-b border-matrix-700">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <a href="/" class="text-lg font-bold font-mono text-neon">Vacilada</a>
            <a href="/" class="text-sm bg-neon text-matrix-900 font-bold px-4 py-2 rounded-lg">Hacer broma</a>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 py-10">
        <a href="/" class="text-sm text-gray-500 hover:text-neon">&larr; Volver al inicio</a>
        <h1 class="text-3xl md:text-4xl font-bold font-mono text-neon mt-4 mb-2">Política de privacidad</h1>
        <p class="text-xs text-gray-500 mb-8">Última actualización: 21 de abril de 2026</p>

        <div class="space-y-6 text-sm leading-relaxed text-gray-300">
            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">1. Qué datos recopilamos</h2>
                <p class="mb-3">Recopilamos únicamente los datos necesarios para operar el servicio:</p>
                <ul class="list-disc pl-5 space-y-2">
                    <li><strong class="text-white">Cuenta:</strong> nombre, correo electrónico, contraseña cifrada y código de referido.</li>
                    <li><strong class="text-white">Pagos:</strong> Stripe procesa los pagos directamente. Vacilada sólo guarda un identificador del cliente y los últimos 4 dígitos de la tarjeta para mostrar en tu panel. Nunca almacenamos el número completo de tarjeta, CVV ni NIP.</li>
                    <li><strong class="text-white">Llamadas:</strong> número de destino, escenario escrito por el usuario, duración, resultado (contestada, no contestada, buzón de voz), transcripción generada por IA y grabación de audio si Twilio la entrega.</li>
                    <li><strong class="text-white">Datos técnicos:</strong> dirección IP, user-agent del navegador, cookies de sesión y referidos, y eventos de uso necesarios para operar el servicio y prevenir fraude.</li>
                </ul>
            </section>

            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">2. Para qué usamos tus datos</h2>
                <ul class="list-disc pl-5 space-y-2">
                    <li>Procesar y realizar las llamadas telefónicas que solicitas.</li>
                    <li>Cobrar créditos, procesar pagos y gestionar reembolsos automáticos.</li>
                    <li>Moderar contenido con inteligencia artificial y revisión humana para bloquear usos prohibidos según nuestros <a href="/terms" class="text-neon hover:underline">términos y condiciones</a>.</li>
                    <li>Detectar y prevenir abuso del servicio (cuentas duplicadas, spam, fraude, violaciones de los términos).</li>
                    <li>Enviarte notificaciones operativas: estado de la llamada, recibo de compra, alertas de seguridad de tu cuenta.</li>
                    <li>Mejorar la calidad del servicio mediante análisis agregado y anónimo del uso de la plataforma.</li>
                </ul>
            </section>

            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">3. Con quién compartimos tus datos</h2>
                <p class="mb-3">Usamos los siguientes procesadores de datos externos, todos con acuerdos de confidencialidad y protección de datos:</p>
                <div class="grid md:grid-cols-2 gap-3">
                    <div class="bg-matrix-900 rounded-lg p-3">
                        <strong class="text-neon">Twilio</strong>
                        <p class="text-xs text-gray-400 mt-1">Realiza las llamadas telefónicas y entrega las grabaciones.</p>
                    </div>
                    <div class="bg-matrix-900 rounded-lg p-3">
                        <strong class="text-neon">ElevenLabs</strong>
                        <p class="text-xs text-gray-400 mt-1">Genera las voces sintéticas en español mexicano.</p>
                    </div>
                    <div class="bg-matrix-900 rounded-lg p-3">
                        <strong class="text-neon">OpenAI</strong>
                        <p class="text-xs text-gray-400 mt-1">Convierte audio a texto en tiempo real (transcripción).</p>
                    </div>
                    <div class="bg-matrix-900 rounded-lg p-3">
                        <strong class="text-neon">Anthropic</strong>
                        <p class="text-xs text-gray-400 mt-1">Genera las respuestas del personaje y modera los escenarios.</p>
                    </div>
                    <div class="bg-matrix-900 rounded-lg p-3">
                        <strong class="text-neon">Stripe</strong>
                        <p class="text-xs text-gray-400 mt-1">Procesa los pagos con tarjeta de crédito y débito.</p>
                    </div>
                    <div class="bg-matrix-900 rounded-lg p-3">
                        <strong class="text-neon">Cloudflare</strong>
                        <p class="text-xs text-gray-400 mt-1">CDN, protección DDoS y seguridad de red.</p>
                    </div>
                </div>
                <p class="mt-4"><strong class="text-white">No vendemos, rentamos ni compartimos datos personales con terceros para fines publicitarios o de marketing.</strong></p>
            </section>

            <x-ad-banner slot="2222222222" format="auto" />

            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">4. Consentimiento de la persona que recibe la llamada</h2>
                <p>Vacilada no contacta directamente a la persona que recibe la broma fuera del momento de la llamada. El usuario que envía la broma declara tener una relación personal con esa persona y asume toda la responsabilidad del consentimiento.</p>
                <p class="mt-3">Si eres una persona que recibió una llamada de Vacilada y deseas que eliminemos tu número de teléfono de nuestros registros, escribe a <a href="mailto:privacidad@vacilada.com" class="text-neon hover:underline">privacidad@vacilada.com</a> y lo procesaremos dentro de 48 horas.</p>
            </section>

            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">5. Retención de datos</h2>
                <ul class="list-disc pl-5 space-y-2">
                    <li><strong class="text-white">Datos de cuenta:</strong> se conservan mientras la cuenta esté activa. Puedes solicitar la eliminación de tu cuenta en cualquier momento.</li>
                    <li><strong class="text-white">Grabaciones y transcripciones:</strong> se almacenan por 90 días desde la fecha de la llamada. Puedes eliminarlas manualmente antes de ese plazo desde tu panel de usuario.</li>
                    <li><strong class="text-white">Registros técnicos (logs):</strong> se retienen hasta 30 días para efectos de seguridad y depuración.</li>
                    <li><strong class="text-white">Datos de pago:</strong> los identificadores de cliente de Stripe se conservan para historial de facturación mientras la cuenta esté activa.</li>
                </ul>
            </section>

            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">6. Tus derechos</h2>
                <p class="mb-3">Conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP) de México, tienes derecho a:</p>
                <ul class="list-disc pl-5 space-y-2">
                    <li><strong class="text-white">Acceso:</strong> solicitar una copia de los datos personales que tenemos sobre ti.</li>
                    <li><strong class="text-white">Rectificación:</strong> solicitar la corrección de datos incorrectos o incompletos.</li>
                    <li><strong class="text-white">Cancelación:</strong> solicitar la eliminación de tus datos personales.</li>
                    <li><strong class="text-white">Oposición:</strong> oponerte al tratamiento de tus datos para fines específicos.</li>
                </ul>
                <p class="mt-3">Para ejercer cualquiera de estos derechos (derechos ARCO), escribe a <a href="mailto:privacidad@vacilada.com" class="text-neon hover:underline">privacidad@vacilada.com</a>. Procesaremos tu solicitud en un plazo máximo de 20 días hábiles.</p>
            </section>

            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">7. Cookies</h2>
                <p>Usamos las siguientes cookies:</p>
                <ul class="list-disc pl-5 space-y-2 mt-2">
                    <li><strong class="text-white">Cookie de sesión:</strong> para mantenerte conectado a tu cuenta mientras navegas.</li>
                    <li><strong class="text-white">Cookie de referido</strong> (<code class="text-neon">vacilada_ref</code>): para atribuir correctamente las invitaciones del programa de referidos.</li>
                    <li><strong class="text-white">Cookies de análisis:</strong> para entender cómo se usa el sitio de forma agregada y anónima.</li>
                </ul>
                <p class="mt-3">No usamos cookies de seguimiento publicitario de terceros.</p>
            </section>

            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">8. Seguridad</h2>
                <p>Implementamos medidas de seguridad técnicas y organizativas para proteger tus datos, incluyendo cifrado en tránsito (HTTPS/TLS), almacenamiento seguro de contraseñas (hashing con bcrypt), acceso restringido a bases de datos, y monitoreo continuo de actividad sospechosa.</p>
            </section>

            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">9. Cambios a esta política</h2>
                <p>Si actualizamos esta política de privacidad, publicaremos la nueva versión en esta página y actualizaremos la fecha al inicio del documento. Los cambios sustanciales que afecten el tratamiento de tus datos se notificarán por correo electrónico a los usuarios registrados con al menos 15 días de anticipación.</p>
            </section>

            <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-3">10. Contacto</h2>
                <p>Para cualquier consulta, solicitud o reclamo relacionado con la privacidad de tus datos:</p>
                <div class="mt-3 space-y-1">
                    <div><strong class="text-neon">Email:</strong> <a href="mailto:privacidad@vacilada.com" class="text-neon hover:underline">privacidad@vacilada.com</a></div>
                    <div><strong class="text-neon">Web:</strong> <a href="/" class="hover:underline">vacilada.com</a></div>
                </div>
            </section>
        </div>
    </main>

    <footer class="border-t border-matrix-700 py-8 mt-12">
        <div class="max-w-4xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-500 mb-6">
                <div>
                    <div class="font-semibold text-gray-400 mb-2">Producto</div>
                    <a href="/bromas" class="block hover:text-neon">Bromas</a>
                    <a href="/trending" class="block hover:text-neon">Trending</a>
                    <a href="/pricing" class="block hover:text-neon">Precios</a>
                </div>
                <div>
                    <div class="font-semibold text-gray-400 mb-2">Empresa</div>
                    <a href="/about" class="block hover:text-neon">Sobre nosotros</a>
                    <a href="/como-funciona" class="block hover:text-neon">Cómo funciona</a>
                    <a href="/press" class="block hover:text-neon">Prensa</a>
                </div>
                <div>
                    <div class="font-semibold text-gray-400 mb-2">Recursos</div>
                    <a href="/preguntas-frecuentes" class="block hover:text-neon">FAQ</a>
                    <a href="/blog" class="block hover:text-neon">Blog</a>
                </div>
                <div>
                    <div class="font-semibold text-gray-400 mb-2">Legal</div>
                    <a href="/terms" class="block hover:text-neon">Términos</a>
                    <a href="/privacy" class="block hover:text-neon">Privacidad</a>
                </div>
            </div>
            <div class="text-center text-xs text-gray-600">Vacilada &middot; Bromas telefónicas con IA &middot; Hecho en México</div>
        </div>
    </footer>
</body>
</html>
