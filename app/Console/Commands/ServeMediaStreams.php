<?php

namespace App\Console\Commands;

use App\WebSockets\MediaStreamServer;
use Illuminate\Console\Command;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;

class ServeMediaStreams extends Command
{
    protected $signature = 'echjokes:stream-server {--port=8081}';
    protected $description = 'Twilio Media Streams WebSocket server';

    public function handle(): int
    {
        $port = (int) $this->option('port');
        $this->info("Starting Media Stream server on port {$port}...");

        $handler = new MediaStreamServer();

        // ReactPHP HTTP server that handles WebSocket upgrade
        $http = new HttpServer(function (\Psr\Http\Message\ServerRequestInterface $request) use ($handler) {
            $path = $request->getUri()->getPath();

            if ($path === '/health') {
                return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
            }

            // Check if this is a WebSocket upgrade request
            $upgrade = strtolower($request->getHeaderLine('Upgrade'));
            if ($upgrade === 'websocket' && str_starts_with($path, '/stream/')) {
                return $handler->handleUpgrade($request);
            }

            return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
        });

        $socket = new SocketServer("0.0.0.0:{$port}");
        $http->listen($socket);

        $this->info("WebSocket server running on ws://0.0.0.0:{$port}");

        return self::SUCCESS;
    }
}
