<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $referrer->name }} te invitó a Vacilada — Bromas telefónicas con IA</title>
    <meta name="description" content="{{ $referrer->name }} te manda Vacilada. Regístrate con su link y los dos ganan 2 bromas gratis." />
    <link rel="canonical" href="{{ url('/r/' . $code) }}" />
    <meta property="og:title" content="{{ $referrer->name }} te invita a Vacilada" />
    <meta property="og:description" content="Regístrate y los dos ganan 2 bromas gratis 😂" />
    <meta property="og:image" content="{{ url('/brand/og-image.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-matrix-900 text-white min-h-screen antialiased">
    <nav class="px-4 py-4">
        <div class="max-w-3xl mx-auto flex justify-between items-center">
            <a href="/" class="text-lg font-bold font-mono text-neon">Vacilada</a>
            <a href="/login?ref={{ $code }}" class="text-sm text-gray-400 hover:text-neon">Ya tengo cuenta</a>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto px-4 py-8 md:py-16 text-center">
        <div class="text-5xl md:text-6xl mb-4 animate-[ring_1s_ease-in-out_infinite]">&#x1F4DE;</div>
        <h1 class="text-3xl md:text-5xl font-bold mb-3">
            <span class="text-neon font-mono">{{ $referrer->name }}</span> te invita a Vacilada
        </h1>
        <p class="text-gray-400 text-lg md:text-xl mb-8">
            Regístrate con su link y los <strong class="text-neon">dos ganan 2 bromas GRATIS</strong> 🎁
        </p>

        <a href="/login?ref={{ $code }}" class="inline-block bg-neon text-matrix-900 font-bold text-lg px-8 py-4 rounded-xl hover:shadow-[var(--shadow-neon-lg)] transition mb-3">
            &#x1F4DE; Regístrate y reclama 2 bromas
        </a>
        <p class="text-xs text-gray-500">Sin tarjeta. Solo tu email.</p>

        @if($bestCalls->count())
        <section class="mt-14 text-left">
            <h2 class="text-lg font-semibold text-white mb-4 text-center">Las vaciladas de {{ $referrer->name }}</h2>
            <div class="space-y-3">
                @foreach($bestCalls as $call)
                <a href="/v/{{ $call->share_slug }}" class="block bg-matrix-800 border border-matrix-600 rounded-xl p-4 hover:border-neon/50 transition">
                    <div class="text-xs text-gray-500 uppercase mb-1">Broma &middot; {{ $call->created_at->diffForHumans() }}</div>
                    <div class="text-sm text-white">
                        @if($call->victim_name)
                            Llamó a <strong class="text-neon">{{ $call->victim_name }}</strong>
                        @else
                            Una broma épica
                        @endif
                    </div>
                    <div class="text-xs text-gray-400 mt-1">{{ \Illuminate\Support\Str::limit($call->custom_joke_prompt, 120) }}</div>
                </a>
                @endforeach
            </div>
        </section>
        @endif

        <section class="mt-14 text-left bg-matrix-800 border border-matrix-600 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-neon mb-2">&#x1F4A1; Cómo funciona</h3>
            <ol class="text-sm text-gray-300 space-y-2 list-decimal pl-5">
                <li>Te registras usando el link de {{ $referrer->name }}</li>
                <li>Haces tu primera broma (gratis al registrarte)</li>
                <li>Cuando la hagas, los dos reciben +2 bromas extra</li>
            </ol>
        </section>
    </main>

    <footer class="border-t border-matrix-700 py-8 mt-12 text-center text-xs text-gray-500">
        Vacilada &middot; Bromas telefónicas con IA &middot; Hecho en México
    </footer>
</body>
</html>
