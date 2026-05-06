<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vacilada - Bromas Telefonicas con IA</title>
    <meta name="description" content="Describe una situacion, la IA llama y hace una broma telefonica en tiempo real. Bromas personalizadas con inteligencia artificial.">

    <meta property="og:title" content="Vacilada — Bromas telefónicas con IA" />
    <meta property="og:description" content="Tú describes la broma, la IA llama. Habla como humano real en español mexicano." />
    <meta property="og:image" content="{{ url('/brand/og-image.png') }}" />
    <meta property="og:url" content="{{ url('/') }}" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Vacilada — Bromas telefónicas con IA" />
    <meta name="twitter:description" content="Tú describes la broma, la IA llama." />
    <meta name="twitter:image" content="{{ url('/brand/og-image.png') }}" />
    <link rel="icon" type="image/png" href="/brand/logo.png" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7978976781623579" crossorigin="anonymous"></script>
    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init','2171220190087137');
    fbq('track','PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=2171220190087137&ev=PageView&noscript=1"/></noscript>
</head>
<body class="bg-matrix-900 text-white min-h-screen antialiased">
    <div id="app"></div>

    <script>
        window.__VACILADA__ = {
            reverb: {
                key: "{{ config('reverb.apps.0.key') }}",
                host: "{{ config('reverb.apps.0.options.host', 'localhost') }}",
                port: {{ config('reverb.apps.0.options.port', 8080) }},
                scheme: "{{ config('reverb.apps.0.options.scheme', 'http') }}",
            },
        };
    </script>
</body>
</html>
