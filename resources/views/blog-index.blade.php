<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog — Vacilada | Bromas telefónicas con IA</title>
    <meta name="description" content="Artículos sobre bromas telefónicas, humor mexicano e inteligencia artificial. El blog oficial de Vacilada." />
    <link rel="canonical" href="{{ url('/blog') }}" />
    <meta property="og:title" content="Blog — Vacilada" />
    <meta property="og:description" content="Artículos sobre humor, tecnología y la cultura mexicana de las bromas telefónicas." />
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

    <main class="max-w-4xl mx-auto px-4 py-10 space-y-12">

        <section>
            <h1 class="text-4xl md:text-5xl font-bold font-mono text-neon mb-3">Blog</h1>
            <p class="text-gray-400 text-lg">Historias, consejos y curiosidades sobre bromas telefónicas con IA</p>
        </section>

        <x-ad-banner slot="9999999999" format="auto" />

        <section>
            <div class="grid md:grid-cols-2 gap-4">
                @foreach ($articles as $article)
                    <a href="/blog/{{ $article['slug'] }}" class="bg-matrix-800 border border-matrix-600 rounded-xl p-5 hover:border-neon/50 transition block">
                        <div class="text-3xl mb-3">{{ $article['emoji'] }}</div>
                        <h2 class="text-lg font-bold mb-2">{{ $article['title'] }}</h2>
                        <p class="text-gray-400 text-sm leading-relaxed mb-3">{{ $article['excerpt'] }}</p>
                        <div class="flex items-center gap-3 text-xs text-gray-500">
                            <span>{{ $article['date'] }}</span>
                            <span>&middot;</span>
                            <span>{{ $article['reading_time'] }} min de lectura</span>
                        </div>
                    </a>
                @endforeach
            </div>

            @if (empty($articles))
                <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-8 text-center">
                    <div class="text-4xl mb-3">📝</div>
                    <p class="text-gray-400">Próximamente publicaremos nuestros primeros artículos. ¡Vuelve pronto!</p>
                </div>
            @endif
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
