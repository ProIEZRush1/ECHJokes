<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $preset->emoji }} {{ $preset->label }} — Broma telefonica con IA | Vacilada</title>
    <meta name="description" content="{{ Str::limit($preset->scenario, 155) }}" />
    <link rel="canonical" href="{{ url('/bromas/' . $preset->slug) }}" />

    <meta property="og:title" content="{{ $preset->emoji }} {{ $preset->label }} — Broma telefonica con IA" />
    <meta property="og:description" content="{{ Str::limit($preset->scenario, 155) }}" />
    <meta property="og:url" content="{{ url('/bromas/' . $preset->slug) }}" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary" />

    <script type="application/ld+json">
    @verbatim {"@context":"https://schema.org","@type":"Product", @endverbatim
    "name": "{{ $preset->label }} — Broma telefonica",
    "description": "{{ addslashes(Str::limit($preset->scenario, 300)) }}",
    @verbatim "brand":{"@type":"Brand","name":"Vacilada"},"offers":{"@type":"Offer","priceCurrency":"MXN","price":"35","availability":"https://schema.org/InStock"}} @endverbatim
    </script>

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
        <div class="text-6xl mb-4">{{ $preset->emoji }}</div>
        <h1 class="text-4xl md:text-5xl font-bold font-mono text-neon mb-3">{{ $preset->label }}</h1>
        <p class="text-gray-400 text-lg mb-8">Broma telefonica automatica con IA en espanol mexicano. La IA llama, habla como un humano real, y te puedes morir de risa con la reaccion.</p>

        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-6 mb-8">
            <h2 class="text-xs text-gray-500 uppercase mb-2">Escenario</h2>
            <p class="text-white leading-relaxed">{{ $preset->scenario }}</p>
        </div>

        <a href="/?preset={{ $preset->slug }}" class="inline-block bg-neon text-matrix-900 font-bold text-lg px-8 py-4 rounded-xl hover:shadow-[var(--shadow-neon-lg)] transition">
            &#x1F4DE; Hacer esta broma ahora
        </a>
        <p class="text-xs text-gray-500 mt-3">Gratis 2 bromas al registrarte. Llamadas reales con IA.</p>

        <x-ad-banner slot="3333333333" format="auto" />

        <section class="mt-14">
            <h2 class="text-2xl font-bold text-white mb-5">Cómo funciona esta broma</h2>
            <ol class="space-y-4 text-gray-300">
                <li class="flex gap-3"><span class="text-neon font-bold">1.</span> Escoge esta broma o <a href="/bromas" class="text-neon hover:underline">elige otra de nuestro catálogo</a>.</li>
                <li class="flex gap-3"><span class="text-neon font-bold">2.</span> Ingresa el número de tu amigo o familiar (México, +52).</li>
                <li class="flex gap-3"><span class="text-neon font-bold">3.</span> La IA llama inmediatamente y actúa como un humano real en español mexicano.</li>
                <li class="flex gap-3"><span class="text-neon font-bold">4.</span> Toda la llamada se graba automáticamente. Escúchala y comparte por WhatsApp.</li>
            </ol>
        </section>

        <section class="mt-14">
            <h2 class="text-2xl font-bold text-white mb-5">Consejos para esta broma</h2>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-6 space-y-3 text-gray-300">
                <p><strong class="text-white">Elige bien a tu víctima:</strong> Esta broma funciona mejor con alguien que conozcas bien. Piensa en quién tendría la reacción más divertida ante este escenario — alguien que se lo tome en serio al principio y se ría después.</p>
                <p><strong class="text-white">El momento importa:</strong> Llama cuando la persona esté disponible y relajada. Evita horarios de trabajo intenso o momentos de estrés — una broma es mejor cuando la persona puede dedicarle atención.</p>
                <p><strong class="text-white">Comparte la risa:</strong> Las mejores bromas son las que todos disfrutan. Después de escuchar la grabación, mándala por WhatsApp para que tu grupo de amigos también se muera de risa.</p>
            </div>
        </section>

        <section class="mt-14">
            <h2 class="text-2xl font-bold text-white mb-5">Preguntas frecuentes</h2>
            <div class="space-y-3">
                <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
                    <h3 class="font-semibold text-neon mb-2">¿La persona sabrá que es una IA?</h3>
                    <p class="text-gray-300 text-sm">La IA usa voces de locutores mexicanos reales y habla con muletillas naturales. Si la persona pregunta "¿eres un robot?", la IA tiene respuestas entrenadas para mantenerse en personaje.</p>
                </div>
                <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
                    <h3 class="font-semibold text-neon mb-2">¿Qué pasa si no contestan?</h3>
                    <p class="text-gray-300 text-sm">Si la llamada no se contesta, va a buzón de voz o está ocupada, tu crédito se reembolsa automáticamente. Solo se cobra si la llamada se completa.</p>
                </div>
                <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
                    <h3 class="font-semibold text-neon mb-2">¿Puedo personalizar el escenario?</h3>
                    <p class="text-gray-300 text-sm">Sí. Puedes usar este escenario tal cual o modificarlo para adaptarlo a tu amigo. También puedes <a href="/" class="text-neon hover:underline">escribir un escenario completamente nuevo</a> desde cero.</p>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-4">¿Más dudas? Visita nuestras <a href="/preguntas-frecuentes" class="text-neon hover:underline">preguntas frecuentes</a>.</p>
        </section>

        @if($related->count())
        <section class="mt-14">
            <h2 class="text-2xl font-bold text-white mb-5">Otras bromas populares</h2>
            <div class="grid md:grid-cols-2 gap-3">
                @foreach($related as $r)
                <a href="/bromas/{{ $r->slug }}" class="bg-matrix-800 border border-matrix-600 hover:border-neon/50 rounded-xl p-4 flex items-start gap-3 transition">
                    <span class="text-2xl">{{ $r->emoji }}</span>
                    <div>
                        <div class="font-semibold text-white">{{ $r->label }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ Str::limit($r->scenario, 80) }}</div>
                    </div>
                </a>
                @endforeach
            </div>
        </section>
        @endif
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
