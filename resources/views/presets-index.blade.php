<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bromas telefonicas con IA — Lista completa | ECHJokes</title>
    <meta name="description" content="Bromas telefonicas automaticas con IA en espanol. Elige una y llama a tu amigo. La IA habla como humano real." />
    <link rel="canonical" href="{{ url('/bromas') }}" />
    <meta property="og:title" content="Bromas telefonicas con IA | ECHJokes" />
    <meta property="og:description" content="Elige tu broma, la IA llama. Espanol mexicano." />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-matrix-900 text-white min-h-screen antialiased">
    <nav class="px-4 py-4 border-b border-matrix-700">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <a href="/" class="text-lg font-bold font-mono text-neon">ECHJokes</a>
            <a href="/" class="text-sm bg-neon text-matrix-900 font-bold px-4 py-2 rounded-lg">Hacer broma</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-10">
        <h1 class="text-4xl md:text-5xl font-bold font-mono text-neon mb-3">Bromas telefonicas</h1>
        <p class="text-gray-400 text-lg mb-8">Todas las bromas disponibles. Elige una y manda a tu amigo.</p>

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
    </main>
</body>
</html>
