<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'api/generate-style',
            'api/ab/*',
            'inbound',
            'joke/*',
            'trial-joke',
            'conversation/*',
            'api/call-transcript',
            'trial',
            'user-api/*',
            'admin-api/*',
            'test/*',
        ]);
        $middleware->web(append: [\App\Http\Middleware\TrackVisitor::class]);
        // We run behind Traefik inside Docker. Trust the reverse proxy so
        // $request->ip() returns the real client IP from X-Forwarded-For
        // (required for trial and registration IP rate-limits).
        $middleware->trustProxies(at: '*', headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
