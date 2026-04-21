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

        <section class="mt-14">
            <h2 class="text-2xl font-bold text-white mb-5">Como funciona?</h2>
            <ol class="space-y-4 text-gray-300">
                <li class="flex gap-3"><span class="text-neon font-bold">1.</span> Escoge esta broma o escribe tu propio escenario.</li>
                <li class="flex gap-3"><span class="text-neon font-bold">2.</span> Ingresa el numero de tu amigo/familiar.</li>
                <li class="flex gap-3"><span class="text-neon font-bold">3.</span> La IA llama y actua como un humano real. Se graba todo.</li>
                <li class="flex gap-3"><span class="text-neon font-bold">4.</span> Escucha la grabacion y comparte por WhatsApp.</li>
            </ol>
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

    <footer class="border-t border-matrix-700 py-8 text-center text-xs text-gray-500">
        Vacilada &middot; Bromas telefonicas con IA &middot; <a href="/bromas" class="hover:text-neon">Todas las bromas</a>
    </footer>
</body>
</html>
