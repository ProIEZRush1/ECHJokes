<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trending — Las vaciladas más virales | Vacilada</title>
    <meta name="description" content="Las bromas telefónicas con IA más virales de Vacilada. Escúchalas y haz la tuya." />
    <link rel="canonical" href="{{ url('/trending') }}" />
    <meta property="og:title" content="Trending en Vacilada 🔥" />
    <meta property="og:description" content="Las vaciladas más chistosas de la semana" />
    <meta property="og:image" content="{{ url('/brand/og-image.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7978976781623579" crossorigin="anonymous"></script>
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','2171220190087137');fbq('track','PageView');</script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=2171220190087137&ev=PageView&noscript=1"/></noscript>

    @verbatim
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ItemList",
      "name": "Vaciladas Trending",
      "description": "Las bromas telefónicas más virales de Vacilada"
    }
    </script>
    @endverbatim
</head>
<body class="bg-matrix-900 text-white min-h-screen antialiased">
    <nav class="px-4 py-4 border-b border-matrix-700">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <a href="/" class="text-lg font-bold font-mono text-neon">Vacilada</a>
            <a href="/" class="text-sm bg-neon text-matrix-900 font-bold px-4 py-2 rounded-lg">Hacer broma</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-10">
        <h1 class="text-4xl md:text-5xl font-bold font-mono text-neon mb-2">🔥 Trending</h1>
        <p class="text-gray-400 text-lg mb-8">Las vaciladas más virales de la semana</p>

        <x-ad-banner slot="4444444444" format="auto" />

        @if($items->count())
            <div class="space-y-3">
                @foreach($items as $item)
                <a href="/v/{{ $item['slug'] }}" class="block bg-matrix-800 border border-matrix-600 hover:border-neon/50 rounded-xl p-4 transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs text-gray-500 uppercase mb-1">{{ $item['date'] }} · {{ $item['creator'] }}</div>
                            <div class="text-base font-semibold text-white mb-1">
                                @if($item['victim_name'])
                                    Le hizo una vacilada a <span class="text-neon">{{ $item['victim_name'] }}</span>
                                @else
                                    Una broma épica
                                @endif
                            </div>
                            <div class="text-sm text-gray-400">{{ $item['scenario'] }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-2xl font-bold text-neon">{{ $item['views'] }}</div>
                            <div class="text-[10px] text-gray-500 uppercase">escuchas</div>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        @else
            <div class="text-center py-16 text-gray-500">
                <p class="mb-3">Aún no hay vaciladas trending.</p>
                <a href="/" class="inline-block bg-neon text-matrix-900 font-bold px-6 py-3 rounded-xl">Haz la primera</a>
            </div>
        @endif
    </main>
</body>
</html>
