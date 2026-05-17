<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $article['title'] }} — Blog | Vacilada</title>
    <meta name="description" content="{{ $article['meta_description'] }}" />
    <link rel="canonical" href="{{ url('/blog/' . $article['slug']) }}" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="{{ $article['title'] }} — Vacilada" />
    <meta property="og:description" content="{{ $article['meta_description'] }}" />
    <meta property="og:url" content="{{ url('/blog/' . $article['slug']) }}" />
    <meta property="og:image" content="{{ url('/brand/og-image.png') }}" />
    <meta property="article:published_time" content="{{ $article['date'] }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7978976781623579" crossorigin="anonymous"></script>
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','2171220190087137');fbq('track','PageView');</script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=2171220190087137&ev=PageView&noscript=1"/></noscript>
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $article['title'],
        'description' => $article['meta_description'],
        'datePublished' => $article['date'],
        'author' => ['@type' => 'Organization', 'name' => 'Vacilada'],
        'publisher' => ['@type' => 'Organization', 'name' => 'Vacilada', 'logo' => ['@type' => 'ImageObject', 'url' => url('/brand/logo.png')]],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => url('/blog/' . $article['slug'])],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
</head>
<body class="bg-matrix-900 text-white min-h-screen antialiased">
    <nav class="px-4 py-4 border-b border-matrix-700">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <a href="/" class="text-lg font-bold font-mono text-neon">Vacilada</a>
            <a href="/" class="text-sm bg-neon text-matrix-900 font-bold px-4 py-2 rounded-lg">Hacer broma</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-10 space-y-10">

        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-500">
            <a href="/blog" class="hover:text-neon">Blog</a>
            <span class="mx-2">&rsaquo;</span>
            <span class="text-gray-400">{{ $article['title'] }}</span>
        </nav>

        <!-- Article header -->
        <header>
            <h1 class="text-3xl md:text-4xl font-bold font-mono text-neon mb-4">{{ $article['title'] }}</h1>
            <div class="flex items-center gap-3 text-sm text-gray-500">
                <span>{{ $article['date'] }}</span>
                <span>&middot;</span>
                <span>{{ $article['reading_time'] }} min de lectura</span>
            </div>
        </header>

        <!-- Article content -->
        <article class="prose prose-invert prose-lg max-w-none prose-headings:font-bold prose-headings:text-white prose-a:text-neon prose-a:no-underline hover:prose-a:underline prose-strong:text-white prose-blockquote:border-neon">
            {!! $article['content'] !!}
        </article>

        <x-ad-banner slot="1010101010" format="auto" />

        <!-- Related articles -->
        @if (!empty($related))
            <section>
                <h2 class="text-2xl font-bold mb-4">Artículos relacionados</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    @foreach ($related as $rel)
                        <a href="/blog/{{ $rel['slug'] }}" class="bg-matrix-800 border border-matrix-600 rounded-xl p-5 hover:border-neon/50 transition block">
                            <div class="text-2xl mb-2">{{ $rel['emoji'] }}</div>
                            <h3 class="font-bold mb-1">{{ $rel['title'] }}</h3>
                            <p class="text-gray-400 text-sm leading-relaxed">{{ $rel['excerpt'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- CTA -->
        <section class="bg-matrix-800 border border-matrix-600 rounded-xl p-8 text-center">
            <h2 class="text-2xl font-bold mb-3">¿Quieres hacer tu propia broma?</h2>
            <p class="text-gray-400 mb-5">Escribe el escenario, elige la voz y la IA hace la llamada por ti.</p>
            <a href="/" class="inline-block bg-neon text-matrix-900 font-bold px-6 py-3 rounded-lg text-lg hover:brightness-110 transition">Hacer broma</a>
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
