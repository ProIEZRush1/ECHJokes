<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ECHJokes - Escucha esta broma telefonica</title>

    <meta property="og:title" content="Escucha esta broma telefonica de ECHJokes!" />
    <meta property="og:description" content="{{ Str::limit($jokeCall->custom_joke_prompt ?? 'Una broma telefonica increible...', 150) }}" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ route('share.show', $jokeCall->session_id) }}" />
    @if($audioUrl ?? $jokeCall->recording_url)
    <meta property="og:audio" content="{{ $audioUrl ?? $jokeCall->recording_url }}" />
    @endif
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="ECHJokes - Broma telefonica con IA" />
    <meta name="twitter:description" content="{{ Str::limit($jokeCall->custom_joke_prompt ?? 'Una broma telefonica increible...', 150) }}" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-matrix-900 text-white min-h-screen antialiased">
    <div id="app"></div>

    <script>
        window.__ECHJOKES__ = {
            reverb: {
                key: "{{ config('reverb.apps.0.key') }}",
                host: "{{ config('reverb.apps.0.options.host', 'localhost') }}",
                port: {{ config('reverb.apps.0.options.port', 8080) }},
                scheme: "{{ config('reverb.apps.0.options.scheme', 'http') }}",
            },
            shareData: @json([
                'scenario' => $jokeCall->custom_joke_prompt,
                'joke_text' => $jokeCall->joke_text,
                'recording_url' => $audioUrl ?? $jokeCall->recording_url,
                'session_id' => $jokeCall->session_id,
            ]),
        };
    </script>
</body>
</html>
