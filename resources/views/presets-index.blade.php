<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bromas telefonicas con IA — Lista completa | Vacilada</title>
    <meta name="description" content="Bromas telefonicas automaticas con IA en espanol. Elige una y llama a tu amigo. La IA habla como humano real." />
    <link rel="canonical" href="{{ url('/bromas') }}" />
    <meta property="og:title" content="Bromas telefonicas con IA | Vacilada" />
    <meta property="og:description" content="Elige tu broma, la IA llama. Espanol mexicano." />
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
            <div class="flex items-center gap-3">
                <a href="/trending" class="text-sm text-gray-400 hover:text-neon transition">🔥 Trending</a>
                <a href="/" class="text-sm bg-neon text-matrix-900 font-bold px-4 py-2 rounded-lg">Hacer broma</a>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-10">
        <h1 class="text-4xl md:text-5xl font-bold font-mono text-neon mb-3">Bromas telefónicas con IA</h1>
        <p class="text-gray-400 text-lg mb-4">Escenarios listos para usar. Elige uno, ingresa el número de tu amigo, y la IA hace la llamada hablando como un humano real en español mexicano.</p>
        <p class="text-gray-500 text-sm mb-8">Cada broma se adapta en tiempo real a la conversación. La IA improvisa, reacciona y se mantiene en personaje. También puedes <a href="/" class="text-neon hover:underline">escribir tu propio escenario</a> desde cero.</p>

        <div class="grid md:grid-cols-2 gap-3">
            @foreach($presets as $preset)
            <a href="/bromas/{{ $preset->slug }}" class="bg-matrix-800 border border-matrix-600 hover:border-neon/50 rounded-xl p-4 flex items-start gap-3 transition">
                <span class="text-3xl">{{ $preset->emoji }}</span>
                <div>
                    <div class="font-semibold text-white">{{ $preset->label }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ Str::limit($preset->scenario, 100) }}</div>
                </div>
            </a>
            @endforeach
        </div>

        <x-ad-banner slot="4444444444" format="auto" />

        <section class="mt-14">
            <h2 class="text-2xl font-bold text-white mb-4">¿Cómo funcionan las bromas telefónicas con IA?</h2>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-6 space-y-3 text-gray-300 text-sm leading-relaxed">
                <p>Vacilada usa inteligencia artificial de última generación para hacer bromas telefónicas que suenan completamente reales. Cuando eliges un escenario de esta lista, la IA lo toma como punto de partida y construye una conversación natural alrededor de él.</p>
                <p>La llamada no es un mensaje pregrabado — es una conversación real, bidireccional, donde la IA escucha lo que dice la otra persona y responde con coherencia, humor y naturalidad. Usa voces de locutores mexicanos reales y habla con muletillas naturales del español mexicano.</p>
                <p>Toda la llamada se graba automáticamente y puedes escucharla, descargarla o compartirla por WhatsApp con un solo clic. Las mejores bromas aparecen en nuestra página de <a href="/trending" class="text-neon hover:underline">trending</a>.</p>
            </div>
        </section>

        <section class="mt-10">
            <h2 class="text-2xl font-bold text-white mb-4">¿No encuentras la broma perfecta?</h2>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-6 text-gray-300 text-sm">
                <p>Estos escenarios son solo el punto de partida. En Vacilada puedes <a href="/" class="text-neon hover:underline">escribir tu propio escenario personalizado</a> — describe cualquier situación y la IA la actuará. Mientras más lo adaptes a tu amigo, mejor será la reacción.</p>
                <p class="mt-3">¿Necesitas inspiración? Lee nuestro artículo con <a href="/blog/ideas-bromas-telefonicas-amigos" class="text-neon hover:underline">7 ideas de bromas que siempre funcionan</a>.</p>
            </div>
        </section>
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
