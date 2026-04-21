<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $title = ($creatorName ?? null) && $jokeCall->victim_name
            ? "{$creatorName} le hizo una vacilada a {$jokeCall->victim_name}"
            : ($jokeCall->victim_name ? "Le hicieron una vacilada a {$jokeCall->victim_name}" : 'Vacilada - Broma telefónica con IA');
        $desc = Str::limit($jokeCall->custom_joke_prompt ?? $jokeCall->joke_text ?? 'Escucha esta broma telefónica hecha con IA en Vacilada', 155);
        $shareUrl = $jokeCall->share_slug ? route('share.v', $jokeCall->share_slug) : route('share.show', $jokeCall->session_id);
    @endphp
    <title>{{ $title }} | Vacilada</title>
    <meta name="description" content="{{ $desc }}" />
    <link rel="canonical" href="{{ $shareUrl }}" />

    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ $desc }}" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ $shareUrl }}" />
    @if($jokeCall->share_slug)
    <meta property="og:image" content="{{ url('/v/' . $jokeCall->share_slug . '/og.svg') }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    @else
    <meta property="og:image" content="{{ url('/brand/og-image.svg') }}" />
    @endif
    @if($audioUrl ?? $jokeCall->recording_url)
    <meta property="og:audio" content="{{ $audioUrl ?? $jokeCall->recording_url }}" />
    @endif
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $title }}" />
    <meta name="twitter:description" content="{{ $desc }}" />
    <meta name="twitter:image" content="{{ url('/brand/og-image.svg') }}" />

    @if($jokeCall->share_slug)
    @php
        $ldData = [
            '@context' => 'https://schema.org',
            '@type' => 'MediaObject',
            'name' => $title,
            'description' => $desc,
            'contentUrl' => $audioUrl ?? $jokeCall->recording_url,
            'encodingFormat' => 'audio/mpeg',
            'uploadDate' => optional($jokeCall->created_at)->toIso8601String(),
            'duration' => $jokeCall->call_duration_seconds ? 'PT' . $jokeCall->call_duration_seconds . 'S' : null,
            'interactionStatistic' => [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/ListenAction',
                'userInteractionCount' => (int) ($jokeCall->share_views ?? 0),
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode(array_filter($ldData), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="/brand/logo.svg" />
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
            shareData: {!! $shareDataJson ?? '{}' !!},
        };
    </script>
</body>
</html>
