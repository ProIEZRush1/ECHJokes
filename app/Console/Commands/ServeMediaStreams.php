<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;

class ServeMediaStreams extends Command
{
    protected $signature = 'echjokes:stream-server {--port=8081}';
    protected $description = 'Twilio Media Streams WebSocket server with ElevenLabs + Deepgram';

    public function handle(): int
    {
        $port = (int) $this->option('port');
        $this->info("Starting Media Stream server on port {$port}...");

        $handler = new \App\WebSockets\MediaStreamServer();

        $http = new HttpServer(function (\Psr\Http\Message\ServerRequestInterface $request) use ($handler) {
            $path = $request->getUri()->getPath();

            if ($path === '/health') {
                return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
            }

            if (str_starts_with($path, '/stream/')) {
                return $handler->handleUpgrade($request);
            }

            return new Response(404);
        });

        $socket = new SocketServer("0.0.0.0:{$port}");
        $http->listen($socket);
        $this->info("Listening on ws://0.0.0.0:{$port}");

        return self::SUCCESS;
    }
}
