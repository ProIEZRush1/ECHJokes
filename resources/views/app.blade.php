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
    <meta property="og:image" content="{{ url('/brand/og-image.svg') }}" />
    <meta property="og:url" content="{{ url('/') }}" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Vacilada — Bromas telefónicas con IA" />
    <meta name="twitter:description" content="Tú describes la broma, la IA llama." />
    <meta name="twitter:image" content="{{ url('/brand/og-image.svg') }}" />
    <link rel="icon" type="image/svg+xml" href="/brand/logo.svg" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
